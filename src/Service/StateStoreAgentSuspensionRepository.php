<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\State\Api\IStateStore;

/** Durable IStateStore-backed suspension repository with one-time resume claims. */
final class StateStoreAgentSuspensionRepository implements IAgentSuspensionRepository {

	private const STATE_PREFIX = 'assistant.agent.suspension.state.';
	private const CLAIM_PREFIX = 'assistant.agent.suspension.claim.';
	private const FORMAT_VERSION = 1;

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

		for ($attempt = 0; $attempt < 3; $attempt++) {
			$handle = $this->createOpaqueToken();
			$reservationToken = $this->createOpaqueToken();
			$now = time();
			$stored = $this->stateStore->setIfNotExists($this->stateKey($handle), [
				'format_version' => self::FORMAT_VERSION,
				'reservation_token' => $reservationToken,
				'created_at' => $now,
				'expires_at' => $now + $ttlSeconds,
				'suspension' => $suspension->toArray()
			], $ttlSeconds);

			if ($stored) {
				$this->stateStore->flush();
				return $handle;
			}
		}

		throw new AgentSuspensionRepositoryException(
			AgentSuspensionRepositoryException::REASON_INVALID_STATE,
			'Unable to allocate a unique agent resume handle.'
		);
	}

	public function claim(string $resumeHandle): AgentSuspensionClaim {
		$this->assertHandle($resumeHandle);
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
			$message = $status === 'consumed'
				? 'Agent resume handle has already been consumed.'
				: 'Agent resume handle is already being processed.';
			throw new AgentSuspensionRepositoryException($reason, $message);
		}

		try {
			$suspension = $this->readSuspension($resumeHandle);
		} catch (\Throwable $e) {
			$this->deleteClaimIfOwned($resumeHandle, $claimToken);
			throw $e;
		}

		return new AgentSuspensionClaim($resumeHandle, $claimToken, $suspension);
	}

	public function release(AgentSuspensionClaim $claim): void {
		$this->assertHandle($claim->getResumeHandle());
		if ($this->deleteClaimIfOwned($claim->getResumeHandle(), $claim->getClaimToken())) {
			$this->stateStore->flush();
		}
	}

	public function consume(AgentSuspensionClaim $claim): void {
		$this->assertHandle($claim->getResumeHandle());
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

		$this->stateStore->delete($this->stateKey($claim->getResumeHandle()));
		$this->stateStore->set($this->claimKey($claim->getResumeHandle()), [
			'status' => 'consumed',
			'consumed_at' => time()
		], $this->replayTtlSeconds);
		$this->stateStore->flush();
	}

	private function readSuspension(string $resumeHandle): AgentSuspension {
		$stored = $this->stateStore->get($this->stateKey($resumeHandle));
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
			$this->stateStore->delete($this->stateKey($resumeHandle));
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_NOT_FOUND,
				'Agent resume handle was not found or has expired.'
			);
		}

		$payload = $stored['suspension'] ?? null;
		if (!is_array($payload)) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_STATE,
				'Stored agent suspension payload is invalid.'
			);
		}

		try {
			return AgentSuspension::fromArray($payload);
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

	private function assertHandle(string $resumeHandle): void {
		if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $resumeHandle)) {
			throw new AgentSuspensionRepositoryException(
				AgentSuspensionRepositoryException::REASON_INVALID_HANDLE,
				'Agent resume handle has an invalid format.'
			);
		}
	}

	private function stateKey(string $resumeHandle): string {
		return self::STATE_PREFIX . hash('sha256', $resumeHandle);
	}

	private function claimKey(string $resumeHandle): string {
		return self::CLAIM_PREFIX . hash('sha256', $resumeHandle);
	}
}
