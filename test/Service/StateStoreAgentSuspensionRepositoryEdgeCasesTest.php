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
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StateStoreAgentSuspensionRepositoryEdgeCasesTest extends TestCase {

	public function testRepositoryRejectsNonpositiveClaimAndReplayTtl(): void {
		foreach ([[0, 10], [10, 0], [-1, 10]] as [$claimTtl, $replayTtl]) {
			try {
				new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore(), $claimTtl, $replayTtl);
				$this->fail('Nonpositive suspension TTL must be rejected.');
			} catch (InvalidArgumentException $e) {
				$this->assertStringContainsString('greater than zero', $e->getMessage());
			}
		}
	}

	public function testCreateRejectsNonpositiveTtlWithoutWritingState(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$this->expectException(InvalidArgumentException::class);
		$repository->create($this->suspension('conversation:1'), 0);
	}

	public function testEmptyScopeHasNoPendingOrHistoricalSuspensions(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$this->assertNull($repository->findPending(' '));
		$this->assertSame([], $repository->findAll(''));
	}

	public function testInvalidResumeHandleFormatIsRejectedBeforeClaimCreation(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		foreach (['', 'invalid', str_repeat('!', 43), str_repeat('A', 44)] as $handle) {
			try {
				$repository->claim($handle);
				$this->fail('Invalid resume handle was accepted.');
			} catch (AgentSuspensionRepositoryException $e) {
				$this->assertSame(AgentSuspensionRepositoryException::REASON_INVALID_HANDLE, $e->getReason());
			}
		}
	}

	public function testClaimCannotBeTakenByTwoConcurrentAttempts(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$handle = $repository->create($this->suspension('conversation:2'), 100);
		$repository->claim($handle);
		$this->expectException(AgentSuspensionRepositoryException::class);
		$this->expectExceptionMessage('already being processed');
		$repository->claim($handle);
	}

	public function testReleaseAllowsASecondClaimWithDifferentToken(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$handle = $repository->create($this->suspension('conversation:3'), 100);
		$first = $repository->claim($handle);
		$repository->release($first);
		$second = $repository->claim($handle);
		$this->assertSame($handle, $second->getResumeHandle());
		$this->assertNotSame($first->getClaimToken(), $second->getClaimToken());
		$repository->release($second);
	}

	public function testUnknownWellFormedResumeHandleDoesNotLeaveClaim(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		try {
			$repository->claim(str_repeat('A', 43));
			$this->fail('Unknown resume handle was accepted.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_NOT_FOUND, $e->getReason());
		}
		$this->assertSame([], $store->listKeys('assistant.agent.suspension.claim.'));
	}

	public function testConsumedResumeHandleCannotBeClaimedAgain(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$handle = $repository->create($this->suspension('conversation:4'), 100);
		$repository->consume($repository->claim($handle));
		$this->expectException(AgentSuspensionRepositoryException::class);
		$this->expectExceptionMessage('already been consumed');
		$repository->claim($handle);
	}

	public function testForgedClaimCannotConsumeAnotherClaimsSuspension(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$suspension = $this->suspension('conversation:5');
		$handle = $repository->create($suspension, 100);
		$legitimate = $repository->claim($handle);
		$forged = new AgentSuspensionClaim($handle, 'wrong-claim-token', $suspension);
		try {
			$repository->consume($forged);
			$this->fail('Foreign claim was accepted.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_ALREADY_CLAIMED, $e->getReason());
		}
		$this->assertNotNull($repository->findPending('conversation:5'));
		$repository->release($legitimate);
	}

	public function testExpiredPendingStateIsRemovedAndVisibleInHistoryAsExpired(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$repository->create($this->suspension('conversation:6'), 100);
		$stateKey = $store->listKeys('assistant.agent.suspension.state.')[0];
		$state = $store->get($stateKey);
		$state['expires_at'] = time() - 1;
		$store->set($stateKey, $state);
		$historyKey = $store->listKeys('assistant.agent.suspension.history.')[0];
		$history = $store->get($historyKey);
		$history['items'][0]['expires_at'] = time() - 1;
		$store->set($historyKey, $history);
		$this->assertNull($repository->findPending('conversation:6'));
		$this->assertSame([], $store->listKeys('assistant.agent.suspension.state.'));
		$history = $repository->findAll('conversation:6');
		$this->assertCount(1, $history);
		$this->assertSame(AgentSuspensionState::LIFECYCLE_EXPIRED, $history[0]->getLifecycle());
	}

	public function testUnsupportedStoredStateVersionIsRejected(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$repository->create($this->suspension('conversation:7'), 100);
		$key = $store->listKeys('assistant.agent.suspension.state.')[0];
		$state = $store->get($key);
		$state['format_version'] = 999;
		$store->set($key, $state);
		try {
			$repository->findPending('conversation:7');
			$this->fail('Unsupported persistent suspension format was accepted.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_INVALID_STATE, $e->getReason());
		}
	}

	public function testCorruptHistoryVersionCannotBeAccepted(): void {
		$store = new SuspensionMemoryStateStore();
		$repository = new StateStoreAgentSuspensionRepository($store);
		$repository->create($this->suspension('conversation:8'), 100);
		$key = $store->listKeys('assistant.agent.suspension.history.')[0];
		$store->set($key, ['format_version' => 999, 'items' => []]);
		try {
			$repository->findAll('conversation:8');
			$this->fail('Unsupported history format was accepted.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_INVALID_STATE, $e->getReason());
		}
	}

	public function testResolutionHistoryCannotExposeSensitiveActionArguments(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$handle = $repository->create($this->suspension('conversation:9'), 100);
		$claim = $repository->claim($handle);
		$repository->consume($claim, new AgentSuspensionResolution([
			new AgentInteractionResponse('interaction-1', AgentInteractionResponse::DECISION_DENY, ['private' => 'secret'], 'private-note')
		], 'user', '2026-11-03T10:00:00+00:00'));
		$history = $repository->findAll('conversation:9');
		$this->assertCount(1, $history);
		$this->assertSame(AgentSuspensionState::LIFECYCLE_RESOLVED, $history[0]->getLifecycle());
		$this->assertSame('', $history[0]->getResumeHandle());
		$this->assertSame([], $history[0]->getResolution()?->getResponses()[0]->getInput());
		$this->assertSame('', $history[0]->getResolution()?->getResponses()[0]->getNote());
	}

	private function suspension(string $scope): AgentSuspension {
		$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'change_value', ['secret' => 'private']);
		$request = new AgentInteractionRequest(
			'interaction-1', AgentInteractionRequest::KIND_APPROVAL, $action,
			str_repeat('a', 64), 'Approve', 'Confirm action.'
		);
		return new AgentSuspension('susp-1', AgentExecutionStatus::AWAITING_APPROVAL,
			[$request], ['node' => 'waiting'], '2026-11-03T10:00:00+00:00', [], $scope);
	}
}
