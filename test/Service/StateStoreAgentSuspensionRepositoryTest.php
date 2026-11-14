<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 *
 * AssistantRuntime provides the shared execution routing for BASE3 agents.
 * It composes runtime registries, sinks, selectors and configuration services.
 * Agent contracts remain in AssistantFoundation and runtime plugins.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/AssistantRuntime
 **********************************************************************/

namespace Base3\AssistantRuntime\Test\Service;

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentSuspensionResolution;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\CoreFoundation\State\Api\IStateStore;
use PHPUnit\Framework\TestCase;

final class StateStoreAgentSuspensionRepositoryTest extends TestCase {

	public function testPendingSuspensionUsesTheSameCanonicalStateAsResume(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$suspension = $this->suspension('susp-1', 'conversation:scope-1');

		$handle = $repository->create($suspension, 900);
		$pending = $repository->findPending('conversation:scope-1');

		$this->assertNotNull($pending);
		$this->assertTrue($pending->isSuspended());
		$this->assertSame($suspension->getStatus(), $pending->getStatus());
		$this->assertSame($handle, $pending->getResumeHandle());
		$this->assertSame(
			array_map(static fn($request): array => $request->toArray(), $suspension->getRequests()),
			array_map(static fn($request): array => $request->toArray(), $pending->getInteractionRequests())
		);

		$claim = $repository->claim($handle);
		$repository->consume($claim);

		$this->assertNull($repository->findPending('conversation:scope-1'));
	}


	public function testResolvedSuspensionRemainsAvailableAsTerminalHistory(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$suspension = $this->suspension('susp-history', 'conversation:scope-history');
		$handle = $repository->create($suspension, 900);
		$claim = $repository->claim($handle);
		$repository->consume($claim, new AgentSuspensionResolution([
			new AgentInteractionResponse('air-1', AgentInteractionResponse::DECISION_APPROVE)
		], 'explicit', '2026-08-27T07:05:00+00:00'));

		$history = $repository->findAll('conversation:scope-history');

		$this->assertCount(1, $history);
		$this->assertSame('susp-history', $history[0]->getId());
		$this->assertSame(AgentSuspensionState::LIFECYCLE_RESOLVED, $history[0]->getLifecycle());
		$this->assertFalse($history[0]->isSuspended());
		$this->assertSame('', $history[0]->getResumeHandle());
		$this->assertSame(AgentSuspensionResolution::OUTCOME_APPROVED, $history[0]->getResolution()?->getOutcome());
		$this->assertSame('explicit', $history[0]->getResolution()?->getSource());
		$this->assertSame([], $history[0]->getResolution()?->getResponses()[0]->getInput());
		$this->assertSame('air-1', $history[0]->getInteractionRequests()[0]['id'] ?? null);
		$this->assertArrayNotHasKey('metadata', $history[0]->getInteractionRequests()[0] ?? []);
	}

	public function testSecondPendingSuspensionForTheSameScopeIsRejected(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$handle = $repository->create($this->suspension('susp-old', 'conversation:scope-1'), 900);

		try {
			$repository->create($this->suspension('susp-new', 'conversation:scope-1'), 900);
			$this->fail('A conversation scope must not have two pending suspension states.');
		} catch (AgentSuspensionRepositoryException $exception) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_INVALID_STATE, $exception->getReason());
		}

		$this->assertSame($handle, $repository->findPending('conversation:scope-1')?->getResumeHandle());
	}

	private function suspension(string $id, string $scopeId): AgentSuspension {
		$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'write_value', ['value' => 'x']);
		$request = new AgentInteractionRequest(
			'air-1',
			AgentInteractionRequest::KIND_APPROVAL,
			$action,
			str_repeat('a', 64),
			'Confirm action',
			'Confirm the mutation.'
		);

		return new AgentSuspension(
			$id,
			AgentExecutionStatus::AWAITING_APPROVAL,
			[$request],
			[],
			'2026-08-07T12:00:00+00:00',
			[],
			$scopeId
		);
	}
}

require_once __DIR__ . '/SuspensionMemoryStateStore.php';
