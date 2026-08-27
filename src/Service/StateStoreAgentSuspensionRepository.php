<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Dto\AgentSuspensionResolution;
use AssistantFoundation\Dto\AgentSuspensionState;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\State\Api\IStateStore;

/** Durable IStateStore-backed suspension repository with one-time resume claims. */
final class StateStoreAgentSuspensionRepository implements IAgentSuspensionRepository {

	private const STATE_PREFIX = 'assistant.agent.suspension.state.';
	private const CLAIM_PREFIX = 'assistant.agent.suspension.claim.';
	private const HISTORY_PREFIX = 'assistant.agent.suspension.history.';
	private const FORMAT_VERSION = 2;
	private const HISTORY_FORMAT_VERSION = 1;
	private const SCOPE_TOKEN_LENGTH = 21;
	private const RESUME_HANDLE_LENGTH = 43;

	public function __construct(
		private readonly IStateStore $stateStore,
		private readonly int $claimTtlSeconds = 30,
		private readonly int $replayTtlSeconds = 86400
	) {
		if ($claimTtlSeconds < 1 || $replayTtlSeconds < 1) {
			throw new \InvalidArgumentException('Suspension claim and replay TTL values must be greater than zero.');
		}
	}

	public function create(AgentSuspension $suspension, int $ttlSeconds): string {
		if ($ttlSeconds < 1) {
			throw new \InvalidArgumentException('Agent suspension TTL must be greater than zero.');
		}

		$scopeId = trim($suspension->getScopeId());
		if ($scopeId === '') {
			$scopeId = $suspension->getId();
		}
		$scopeToken = $this->scopeToken($scopeId);
		$resumeHandle = $scopeToken . substr($this->createOpaqueToken(), 0, self::RESUME_HANDLE_LENGTH - self::SCOPE_TOKEN_LENGTH);
		$now = time();
		$expiresAt = $now + $ttlSeconds;
		$created = $this->stateStore->setIfNotExists($this->stateKey($scopeToken), [
			'format_version' => self::FORMAT_VERSION,
			'resume_handle' => $resumeHandle,
			'created_at' => $now,
			'expires_at' => $expiresAt,
			'suspension' => $suspension->toArray()
		], $ttlSeconds);
		if (!$created) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_STATE,
				'Agent suspension scope already has a pending suspension.'
			);
		}

		try {
			$this->appendHistory($scopeToken, $resumeHandle, $suspension, $now, $expiresAt);
			$this->stateStore->flush();
		} catch (\Throwable $e) {
			$this->stateStore->delete($this->stateKey($scopeToken));
			try {
				$this->stateStore->flush();
			} catch (\Throwable) {
			}
			throw $e;
		}

		return $resumeHandle;
	}

	public function findPending(string $scopeId): ?AgentSuspensionState {
		$scopeId = trim($scopeId);
		if ($scopeId === '') {
			return null;
		}

		$scopeToken = $this->scopeToken($scopeId);
		try {
			[$resumeHandle, $suspension, $createdAt, $expiresAt] = $this->readStoredSuspension($scopeToken);
			return new AgentSuspensionState(
				true,
				$suspension->getStatus(),
				$suspension->getRequests(),
				$resumeHandle,
				$this->formatTimestamp($createdAt),
				$this->formatTimestamp($expiresAt),
				AgentSuspensionState::LIFECYCLE_ACTIVE,
				null,
				$suspension->getId()
			);
		} catch (AgentSuspensionRepositoryException $e) {
			if ($e->getReason() === AgentSuspensionRepositoryException::REASON_NOT_FOUND) {
				$this->expireHistory($scopeToken);
				return null;
			}
			throw $e;
		}
	}

	public function findAll(string $scopeId): array {
		$scopeId = trim($scopeId);
		if ($scopeId === '') {
			return [];
		}

		$scopeToken = $this->scopeToken($scopeId);
		$this->expireHistory($scopeToken);
		$history = $this->readHistory($scopeToken);
		$items = is_array($history['items'] ?? null) ? $history['items'] : [];

		if ($items === []) {
			try {
				[$resumeHandle, $suspension, $createdAt, $expiresAt] = $this->readStoredSuspension($scopeToken);
				return [new AgentSuspensionState(
					true,
					$suspension->getStatus(),
					$suspension->getRequests(),
					$resumeHandle,
					$this->formatTimestamp($createdAt),
					$this->formatTimestamp($expiresAt),
					AgentSuspensionState::LIFECYCLE_ACTIVE,
					null,
					$suspension->getId()
				)];
			} catch (AgentSuspensionRepositoryException $e) {
				if ($e->getReason() === AgentSuspensionRepositoryException::REASON_NOT_FOUND) {
					return [];
				}
				throw $e;
			}
		}

		$result = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				throw new AgentSuspensionRepositoryException(
					AgentSuspensionRepositoryException::REASON_INVALID_STATE,
					'Stored agent suspension history contains an invalid item.'
				);
			}
			$result[] = $this->historyState($item);
		}

		return $result;
	}

	public function claim(string $resumeHandle): AgentSuspensionClaim {
		$scopeToken = $this->scopeTokenFromHandle($resumeHandle);
		$claimToken = $this->createOpaqueToken();
		$claimKey = $this->claimKey($resumeHandle);
		$claimed = $this->stateStore->setIfNotExists($claimKey, [
			'status' => 'claimed',
			'claim_token' => $claimToken,
			'claimed_at' => time()
		], $this->claimTtlSeconds);

		if (!$claimed) {
			$claim = $this->stateStore->get($claimKey, []);
			$status = is_array($claim) ? (string)($claim['status'] ?? '') : '';
			$reason = $status === 'consumed'
				? AgentSuspensionRepositoryException::REASON_ALREADY_CONSUMED
				: AgentSuspensionRepositoryException::REASON_ALREADY_CLAIMED;
			throw new AgentSuspensionRepositoryException(
				$reason,
				$status === 'consumed'
					? 'Agent resume handle has already been consumed.'
					: 'Agent resume handle is already being processed.'
			);
		}

		try {
			[$storedHandle, $suspension] = $this->readStoredSuspension($scopeToken);
			if (!hash_equals($storedHandle, $resumeHandle)) {
				throw new AgentSuspensionRepositoryException(
					AgentSuspensionRepositoryException::REASON_NOT_FOUND,
					'Agent resume handle was not found or has expired.'
				);
			}
		} catch (\Throwable $e) {
			$this->deleteClaimIfOwned($resumeHandle, $claimToken);
			if (
				$e instanceof AgentSuspensionRepositoryException
				&& $e->getReason() === AgentSuspensionRepositoryException::REASON_NOT_FOUND
			) {
				$this->expireHistory($scopeToken);
			}
			throw $e;
		}

		return new AgentSuspensionClaim($resumeHandle, $claimToken, $suspension);
	}

	public function release(AgentSuspensionClaim $claim): void {
		$this->scopeTokenFromHandle($claim->getResumeHandle());
		if ($this->deleteClaimIfOwned($claim->getResumeHandle(), $claim->getClaimToken())) {
			$this->stateStore->flush();
		}
	}

	public function consume(
		AgentSuspensionClaim $claim,
		?AgentSuspensionResolution $resolution = null
	): void {
		$scopeToken = $this->scopeTokenFromHandle($claim->getResumeHandle());
		$storedClaim = $this->stateStore->get($this->claimKey($claim->getResumeHandle()), []);
		if (!$this->isOwnedActiveClaim($storedClaim, $claim->getClaimToken())) {
			$status = is_array($storedClaim) ? (string)($storedClaim['status'] ?? '') : '';
			$reason = $status === 'consumed'
				? AgentSuspensionRepositoryException::REASON_ALREADY_CONSUMED
				: AgentSuspensionRepositoryException::REASON_ALREADY_CLAIMED;
			throw new AgentSuspensionRepositoryException(
				$reason,
				'Agent suspension claim is no longer active or is owned by another resume attempt.'
			);
		}

		$resolution ??= new AgentSuspensionResolution([], 'unknown', gmdate('c'));
		$stored = $this->stateStore->get($this->stateKey($scopeToken), []);
		if (is_array($stored) && hash_equals((string)($stored['resume_handle'] ?? ''), $claim->getResumeHandle())) {
			$this->stateStore->delete($this->stateKey($scopeToken));
		}
		$this->stateStore->set($this->claimKey($claim->getResumeHandle()), [
			'status' => 'consumed',
			'consumed_at' => time()
		], $this->replayTtlSeconds);
		$this->resolveHistory($scopeToken, $claim, $resolution);
		$this->stateStore->flush();
	}

	/** @return array{0:string,1:AgentSuspension,2:int,3:int} */
	private function readStoredSuspension(string $scopeToken): array {
		$stateKey = $this->stateKey($scopeToken);
		$stored = $this->stateStore->get($stateKey);
		if (!is_array($stored)) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_NOT_FOUND,
				'Agent resume handle was not found or has expired.'
			);
		}
		if ((int)($stored['format_version'] ?? 0) !== self::FORMAT_VERSION) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_STATE,
				'Stored agent suspension uses an unsupported format version.'
			);
		}
		$createdAt = (int)($stored['created_at'] ?? 0);
		$expiresAt = (int)($stored['expires_at'] ?? 0);
		if ($expiresAt < 1 || $expiresAt <= time()) {
			$this->stateStore->delete($stateKey);
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_NOT_FOUND,
				'Agent resume handle was not found or has expired.'
			);
		}
		$resumeHandle = trim((string)($stored['resume_handle'] ?? ''));
		$payload = $stored['suspension'] ?? null;
		if ($resumeHandle === '' || !is_array($payload)) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_STATE,
				'Stored agent suspension payload is invalid.'
			);
		}
		try {
			return [$resumeHandle, AgentSuspension::fromArray($payload), $createdAt, $expiresAt];
		} catch (\Throwable $e) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_STATE,
				'Stored agent suspension payload could not be restored.',
				$e
			);
		}
	}

	private function appendHistory(
		string $scopeToken,
		string $resumeHandle,
		AgentSuspension $suspension,
		int $createdAt,
		int $expiresAt
	): void {
		$history = $this->readHistory($scopeToken);
		$items = is_array($history['items'] ?? null) ? $history['items'] : [];
		$items[] = [
			'id' => $suspension->getId(),
			'status' => $suspension->getStatus(),
			'lifecycle' => AgentSuspensionState::LIFECYCLE_ACTIVE,
			'resume_handle' => $resumeHandle,
			'created_at' => $createdAt,
			'expires_at' => $expiresAt,
			'interaction_requests' => array_map(
				fn(AgentInteractionRequest $request): array => $this->historyRequest($request),
				$suspension->getRequests()
			),
			'resolution' => null
		];
		$this->writeHistory($scopeToken, $items);
	}

	private function resolveHistory(
		string $scopeToken,
		AgentSuspensionClaim $claim,
		AgentSuspensionResolution $resolution
	): void {
		$history = $this->readHistory($scopeToken);
		$items = is_array($history['items'] ?? null) ? $history['items'] : [];
		$updated = false;
		foreach ($items as &$item) {
			if (!is_array($item)) {
				continue;
			}
			if (!hash_equals((string)($item['resume_handle'] ?? ''), $claim->getResumeHandle())) {
				continue;
			}
			$item['lifecycle'] = AgentSuspensionState::LIFECYCLE_RESOLVED;
			$item['resolution'] = $this->historyResolution($resolution);
			$updated = true;
			break;
		}
		unset($item);

		if (!$updated) {
			$suspension = $claim->getSuspension();
			$createdAt = strtotime($suspension->getCreatedAt()) ?: time();
			$items[] = [
				'id' => $suspension->getId(),
				'status' => $suspension->getStatus(),
				'lifecycle' => AgentSuspensionState::LIFECYCLE_RESOLVED,
				'resume_handle' => $claim->getResumeHandle(),
				'created_at' => $createdAt,
				'expires_at' => 0,
				'interaction_requests' => array_map(
					fn(AgentInteractionRequest $request): array => $this->historyRequest($request),
					$suspension->getRequests()
				),
				'resolution' => $this->historyResolution($resolution)
			];
		}
		$this->writeHistory($scopeToken, $items);
	}

	private function expireHistory(string $scopeToken): void {
		$history = $this->readHistory($scopeToken);
		$items = is_array($history['items'] ?? null) ? $history['items'] : [];
		$now = time();
		$changed = false;
		foreach ($items as &$item) {
			if (!is_array($item)) {
				continue;
			}
			if (($item['lifecycle'] ?? null) !== AgentSuspensionState::LIFECYCLE_ACTIVE) {
				continue;
			}
			$expiresAt = (int)($item['expires_at'] ?? 0);
			if ($expiresAt > 0 && $expiresAt <= $now) {
				$item['lifecycle'] = AgentSuspensionState::LIFECYCLE_EXPIRED;
				$changed = true;
			}
		}
		unset($item);

		if ($changed) {
			$this->writeHistory($scopeToken, $items);
			$this->stateStore->flush();
		}
	}

	/** @return array<string,mixed> */
	private function readHistory(string $scopeToken): array {
		$history = $this->stateStore->get($this->historyKey($scopeToken), []);
		if ($history === [] || $history === null) {
			return [
				'format_version' => self::HISTORY_FORMAT_VERSION,
				'items' => []
			];
		}
		if (!is_array($history) || (int)($history['format_version'] ?? 0) !== self::HISTORY_FORMAT_VERSION) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_STATE,
				'Stored agent suspension history uses an unsupported format.'
			);
		}
		return $history;
	}

	/** @param array<int,array<string,mixed>> $items */
	private function writeHistory(string $scopeToken, array $items): void {
		$this->stateStore->set($this->historyKey($scopeToken), [
			'format_version' => self::HISTORY_FORMAT_VERSION,
			'items' => array_values($items)
		]);
	}

	/** @param array<string,mixed> $item */
	private function historyState(array $item): AgentSuspensionState {
		$requests = [];
		foreach (($item['interaction_requests'] ?? []) as $request) {
			if (!is_array($request)) {
				throw new AgentSuspensionRepositoryException(
					AgentSuspensionRepositoryException::REASON_INVALID_STATE,
					'Stored agent suspension history contains an invalid interaction request.'
				);
			}
			$requests[] = [
				'id' => trim((string)($request['id'] ?? '')),
				'kind' => trim((string)($request['kind'] ?? '')),
				'title' => trim((string)($request['title'] ?? '')),
				'message' => trim((string)($request['message'] ?? '')),
				'summary' => is_array($request['summary'] ?? null) ? $request['summary'] : [],
				'risk' => trim((string)($request['risk'] ?? ''))
			];
		}

		$lifecycle = trim((string)($item['lifecycle'] ?? AgentSuspensionState::LIFECYCLE_ACTIVE));
		$resolution = null;
		if ($lifecycle === AgentSuspensionState::LIFECYCLE_RESOLVED && is_array($item['resolution'] ?? null)) {
			$resolution = AgentSuspensionResolution::fromArray($item['resolution']);
		}
		$suspended = $lifecycle === AgentSuspensionState::LIFECYCLE_ACTIVE;

		return new AgentSuspensionState(
			$suspended,
			trim((string)($item['status'] ?? '')),
			$requests,
			$suspended ? trim((string)($item['resume_handle'] ?? '')) : '',
			$this->formatTimestamp((int)($item['created_at'] ?? 0)),
			$this->formatTimestamp((int)($item['expires_at'] ?? 0)),
			$lifecycle,
			$resolution,
			trim((string)($item['id'] ?? ''))
		);
	}

	/** @return array<string,mixed> */
	private function historyResolution(AgentSuspensionResolution $resolution): array {
		return [
			'outcome' => $resolution->getOutcome(),
			'source' => $resolution->getSource(),
			'resolved_at' => $resolution->getResolvedAt(),
			'responses' => array_map(
				static fn(AgentInteractionResponse $response): array => [
					'request_id' => $response->getRequestId(),
					'decision' => $response->getDecision(),
					'input' => [],
					'note' => '',
					'metadata' => []
				],
				$resolution->getResponses()
			)
		];
	}

	/** @return array<string,mixed> */
	private function historyRequest(AgentInteractionRequest $request): array {
		return [
			'id' => $request->getId(),
			'kind' => $request->getKind(),
			'title' => $request->getTitle(),
			'message' => $request->getMessage(),
			'summary' => $request->getSummary(),
			'risk' => $request->getRisk()
		];
	}

	private function deleteClaimIfOwned(string $resumeHandle, string $claimToken): bool {
		$claimKey = $this->claimKey($resumeHandle);
		$storedClaim = $this->stateStore->get($claimKey, []);
		if (!$this->isOwnedActiveClaim($storedClaim, $claimToken)) {
			return false;
		}
		return $this->stateStore->delete($claimKey);
	}

	private function isOwnedActiveClaim(mixed $storedClaim, string $claimToken): bool {
		if (!is_array($storedClaim) || ($storedClaim['status'] ?? null) !== 'claimed') {
			return false;
		}
		$storedToken = (string)($storedClaim['claim_token'] ?? '');
		return $storedToken !== '' && hash_equals($storedToken, $claimToken);
	}

	private function scopeToken(string $scopeId): string {
		$token = rtrim(strtr(base64_encode(hash('sha256', $scopeId, true)), '+/', '-_'), '=');
		return substr($token, 0, self::SCOPE_TOKEN_LENGTH);
	}

	private function scopeTokenFromHandle(string $resumeHandle): string {
		if (preg_match('/^[A-Za-z0-9_-]{' . self::RESUME_HANDLE_LENGTH . '}$/', $resumeHandle) !== 1) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_HANDLE,
				'Agent resume handle has an invalid format.'
			);
		}
		return substr($resumeHandle, 0, self::SCOPE_TOKEN_LENGTH);
	}

	private function createOpaqueToken(): string {
		try {
			$bytes = random_bytes(32);
		} catch (\Throwable $e) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_UNAVAILABLE,
				'Cryptographically secure resume tokens are unavailable.',
				$e
			);
		}
		return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
	}

	private function formatTimestamp(int $timestamp): string {
		return $timestamp > 0 ? gmdate('c', $timestamp) : '';
	}

	private function stateKey(string $scopeToken): string {
		return self::STATE_PREFIX . $scopeToken;
	}

	private function claimKey(string $resumeHandle): string {
		return self::CLAIM_PREFIX . hash('sha256', $resumeHandle);
	}

	private function historyKey(string $scopeToken): string {
		return self::HISTORY_PREFIX . $scopeToken;
	}
}
