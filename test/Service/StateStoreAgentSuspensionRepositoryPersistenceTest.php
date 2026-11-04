<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Dto\AgentSuspensionResolution;
use AssistantFoundation\Dto\AgentSuspensionState;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\State\Api\IStateStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StateStoreAgentSuspensionRepositoryPersistenceTest extends TestCase {

	public function testMissingScopeUsesSuspensionIdAsAuthoritativeKey(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$handle = $repository->create($this->suspension('resume-id', ''), 120);
		$this->assertSame($handle, $repository->findPending('resume-id')?->getResumeHandle());
		$this->assertNull($repository->findPending('other-id'));
	}

	public function testDifferentConversationScopesRemainIndependent(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$first = $repository->create($this->suspension('first', 'scope:first'), 120);
		$second = $repository->create($this->suspension('second', 'scope:second'), 120);
		$repository->consume($repository->claim($first));
		$this->assertNull($repository->findPending('scope:first'));
		$this->assertSame($second, $repository->findPending('scope:second')?->getResumeHandle());
		$this->assertSame(AgentSuspensionState::LIFECYCLE_RESOLVED, $repository->findAll('scope:first')[0]->getLifecycle());
		$this->assertSame(AgentSuspensionState::LIFECYCLE_ACTIVE, $repository->findAll('scope:second')[0]->getLifecycle());
	}

	public function testMissingHistoryIsReconstructedFromCurrentPendingState(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$handle = $repository->create($this->suspension('current', 'scope:pending'), 120);
		$store->delete($this->onlyKey($store, 'assistant.agent.suspension.history.'));
		$states = $repository->findAll('scope:pending');
		$this->assertCount(1, $states);
		$this->assertTrue($states[0]->isSuspended());
		$this->assertSame($handle, $states[0]->getResumeHandle());
		$this->assertSame('current', $states[0]->getId());
	}

	public function testMissingHistoryAndMissingPendingStateReturnEmptyList(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$repository->create($this->suspension('pending', 'scope:empty'), 120);
		$store->delete($this->onlyKey($store, 'assistant.agent.suspension.state.'));
		$store->delete($this->onlyKey($store, 'assistant.agent.suspension.history.'));
		$this->assertSame([], $repository->findAll('scope:empty'));
	}

	public function testConsumptionReconstructsResolvedHistoryIfRecordDisappears(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$handle = $repository->create($this->suspension('resolved', 'scope:rebuild'), 120);
		$store->delete($this->onlyKey($store, 'assistant.agent.suspension.history.'));
		$repository->consume($repository->claim($handle));
		$history = $repository->findAll('scope:rebuild');
		$this->assertCount(1, $history);
		$this->assertSame('resolved', $history[0]->getId());
		$this->assertSame(AgentSuspensionState::LIFECYCLE_RESOLVED, $history[0]->getLifecycle());
		$this->assertSame('unknown', $history[0]->getResolution()?->getSource());
		$this->assertSame('', $history[0]->getResumeHandle());
	}

	public function testWrongButWellFormedHandleCannotClaimSameScope(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$handle = $repository->create($this->suspension('stored', 'scope:secure'), 120);
		$last = substr($handle, -1);
		$forged = substr($handle, 0, -1) . ($last === 'A' ? 'B' : 'A');
		try {
			$repository->claim($forged);
			$this->fail('Forged resume handle must not claim a valid suspension.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_NOT_FOUND, $e->getReason());
		}
		$this->assertSame([], $store->listKeys('assistant.agent.suspension.claim.'));
		$this->assertSame($handle, $repository->findPending('scope:secure')?->getResumeHandle());
	}

	public function testForgedReleaseDoesNotUnlockAnotherClaimsLease(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$suspension = $this->suspension('stored', 'scope:owner');
		$handle = $repository->create($suspension, 120);
		$real = $repository->claim($handle);
		$repository->release(new AgentSuspensionClaim($handle, 'other-token', $suspension));
		$this->assertCount(1, $store->listKeys('assistant.agent.suspension.claim.'));
		$repository->consume($real);
		$this->assertNull($repository->findPending('scope:owner'));
	}

	public function testConsumedLeaseCannotBeUsedToConsumeTwice(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$handle = $repository->create($this->suspension('stored', 'scope:replay'), 120);
		$claim = $repository->claim($handle);
		$repository->consume($claim);
		try {
			$repository->consume($claim);
			$this->fail('Consumed claim must never be reusable.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_ALREADY_CONSUMED, $e->getReason());
		}
	}

	public function testExpiredClaimIsRejectedWithoutConsumingPendingState(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$handle = $repository->create($this->suspension('stored', 'scope:claim'), 120);
		$claim = $repository->claim($handle);
		$key = $this->onlyKey($store, 'assistant.agent.suspension.claim.');
		$store->delete($key);
		try {
			$repository->consume($claim);
			$this->fail('Lost claim must not be accepted.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_ALREADY_CLAIMED, $e->getReason());
		}
		$this->assertSame($handle, $repository->findPending('scope:claim')?->getResumeHandle());
	}

	public function testInvalidStoredSuspensionPayloadIsRejected(): void {
		foreach (['not-array', ['id' => 'invalid'], []] as $payload) {
			$store = new SuspensionMemoryStateStore();
			$repository = new StateStoreAgentSuspensionRepository($store);
			$repository->create($this->suspension('stored', 'scope:broken'), 120);
			$key = $this->onlyKey($store, 'assistant.agent.suspension.state.');
			$raw = $store->get($key);
			$raw['suspension'] = $payload;
			$store->set($key, $raw);
			try {
				$repository->findPending('scope:broken');
				$this->fail('Corrupted pending suspension must not be returned.');
			} catch (AgentSuspensionRepositoryException $e) {
				$this->assertSame(AgentSuspensionRepositoryException::REASON_INVALID_STATE, $e->getReason());
			}
		}
	}

	public function testMissingStoredResumeHandleIsRejected(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$repository->create($this->suspension('stored', 'scope:missing-handle'), 120);
		$key = $this->onlyKey($store, 'assistant.agent.suspension.state.');
		$raw = $store->get($key);
		$raw['resume_handle'] = '';
		$store->set($key, $raw);
		$this->expectException(AgentSuspensionRepositoryException::class);
		$this->expectExceptionMessage('payload is invalid');
		$repository->findPending('scope:missing-handle');
	}

	public function testHistoryRejectsNonArrayItems(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$repository->create($this->suspension('stored', 'scope:history'), 120);
		$key = $this->onlyKey($store, 'assistant.agent.suspension.history.');
		$history = $store->get($key);
		$history['items'] = ['invalid'];
		$store->set($key, $history);
		$this->expectException(AgentSuspensionRepositoryException::class);
		$this->expectExceptionMessage('invalid item');
		$repository->findAll('scope:history');
	}

	public function testHistoryRejectsMalformedInteractionRequest(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$repository->create($this->suspension('stored', 'scope:history-request'), 120);
		$key = $this->onlyKey($store, 'assistant.agent.suspension.history.');
		$history = $store->get($key);
		$history['items'][0]['interaction_requests'] = ['not-a-request'];
		$store->set($key, $history);
		$this->expectException(AgentSuspensionRepositoryException::class);
		$this->expectExceptionMessage('invalid interaction request');
		$repository->findAll('scope:history-request');
	}

	public function testResolvedHistoryIsNotChangedByExpirationMaintenance(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$handle = $repository->create($this->suspension('stored', 'scope:terminal'), 120);
		$repository->consume($repository->claim($handle));
		$key = $this->onlyKey($store, 'assistant.agent.suspension.history.');
		$history = $store->get($key);
		$history['items'][0]['expires_at'] = time() - 3600;
		$store->set($key, $history);
		$this->assertSame(AgentSuspensionState::LIFECYCLE_RESOLVED, $repository->findAll('scope:terminal')[0]->getLifecycle());
	}

	public function testHistoryFailureDuringCreationRemovesNewPendingState(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$handle = $repository->create($this->suspension('first', 'scope:rollback'), 120);
		$repository->consume($repository->claim($handle));
		$key = $this->onlyKey($store, 'assistant.agent.suspension.history.');
		$store->set($key, ['format_version' => 999, 'items' => []]);
		try {
			$repository->create($this->suspension('second', 'scope:rollback'), 120);
			$this->fail('Invalid history must stop creation.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_INVALID_STATE, $e->getReason());
		}
		$this->assertSame([], $store->listKeys('assistant.agent.suspension.state.'));
	}

	public function testFailedFlushDuringCreationRollsBackState(): void {
		$deletedKeys = [];
		$store = $this->createStub(IStateStore::class);
		$store->method('get')->willReturn([]);
		$store->method('setIfNotExists')->willReturn(true);
		$store->method('delete')->willReturnCallback(static function(string $key) use (&$deletedKeys): bool {
			$deletedKeys[] = $key;
			return true;
		});
		$store->method('flush')->willThrowException(new RuntimeException('storage unavailable'));
		$repository = new StateStoreAgentSuspensionRepository($store);
		try {
			$repository->create($this->suspension('stored', 'scope:storage-error'), 120);
			$this->fail('Creation must propagate the failed flush.');
		} catch (RuntimeException $e) {
			$this->assertSame('storage unavailable', $e->getMessage());
		}
		$this->assertCount(1, $deletedKeys);
		$this->assertStringContainsString('assistant.agent.suspension.state.', $deletedKeys[0]);
	}

	private function onlyKey(SuspensionMemoryStateStore $store, string $prefix): string {
		$keys = $store->listKeys($prefix);
		$this->assertCount(1, $keys);
		return $keys[0];
	}

	private function suspension(string $id, string $scope): AgentSuspension {
		$request = new AgentInteractionRequest(
			'interaction-1',
			AgentInteractionRequest::KIND_APPROVAL,
			new AgentAction('call', AgentAction::TYPE_TOOL_CALL, 'update', ['secret' => 'private']),
			str_repeat('a', 64),
			'Approve',
			'Please confirm.'
		);
		return new AgentSuspension(
			$id,
			AgentExecutionStatus::AWAITING_APPROVAL,
			[$request],
			['opaque' => 'private'],
			'2026-11-03T10:00:00+00:00',
			[],
			$scope
		);
	}
}
