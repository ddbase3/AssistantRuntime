<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Dto\AgentSuspensionState;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\State\Api\IStateStore;

/** Durable IStateStore-backed suspension repository with one-time resume claims. */
final class StateStoreAgentSuspensionRepository implements IAgentSuspensionRepository {

	private const STATE_PREFIX = 'assistant.agent.suspension.state.';
	private const CLAIM_PREFIX = 'assistant.agent.suspension.claim.';
	private const FORMAT_VERSION = 2;
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
		$created = $this->stateStore->setIfNotExists($this->stateKey($scopeToken), [
			'format_version' => self::FORMAT_VERSION,
			'resume_handle' => $resumeHandle,
			'created_at' => $now,
			'expires_at' => $now + $ttlSeconds,
			'suspension' => $suspension->toArray()
		], $ttlSeconds);
		if (!$created) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_STATE,
				'Agent suspension scope already has a pending suspension.'
			);
		}
		$this->stateStore->flush();

		return $resumeHandle;
	}

	public function findPending(string $scopeId): ?AgentSuspensionState {
		$scopeId = trim($scopeId);
		if ($scopeId === '') {
			return null;
		}

		try {
			[$resumeHandle, $suspension] = $this->readStoredSuspension($this->scopeToken($scopeId));
			return new AgentSuspensionState(
				true,
				$suspension->getStatus(),
				$suspension->getRequests(),
				$resumeHandle
			);
		} catch (AgentSuspensionRepositoryException $e) {
			if ($e->getReason() === AgentSuspensionRepositoryException::REASON_NOT_FOUND) {
				return null;
			}
			throw $e;
		}
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

	public function consume(AgentSuspensionClaim $claim): void {
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

		$stored = $this->stateStore->get($this->stateKey($scopeToken), []);
		if (is_array($stored) && hash_equals((string)($stored['resume_handle'] ?? ''), $claim->getResumeHandle())) {
			$this->stateStore->delete($this->stateKey($scopeToken));
		}
		$this->stateStore->set($this->claimKey($claim->getResumeHandle()), [
			'status' => 'consumed',
			'consumed_at' => time()
		], $this->replayTtlSeconds);
		$this->stateStore->flush();
	}

	/** @return array{0:string,1:AgentSuspension} */
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
			return [$resumeHandle, AgentSuspension::fromArray($payload)];
		} catch (\Throwable $e) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_STATE,
				'Stored agent suspension payload could not be restored.',
				$e
			);
		}
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

	private function stateKey(string $scopeToken): string {
		return self::STATE_PREFIX . $scopeToken;
	}

	private function claimKey(string $resumeHandle): string {
		return self::CLAIM_PREFIX . hash('sha256', $resumeHandle);
	}
}
