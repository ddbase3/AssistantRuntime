<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentConfirmableToolSet;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantRuntime\Service\CompositeAgentToolSet;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CompositeAgentToolSetTest extends TestCase {

	public function testEmptyCompositeHasNoCapabilitiesOrWarnings(): void {
		$set = new CompositeAgentToolSet();
		$this->assertSame([], $set->getCatalog()->names());
		$this->assertSame([], $set->getWarnings());
	}

	public function testCombinesSeparateCatalogsAndDeduplicatesWarnings(): void {
		$first = $this->toolSet('read', [' warning ', 'shared', '', 'shared']);
		$second = $this->toolSet('write', ['shared', ' second ']);
		$composite = new CompositeAgentToolSet([$first, $second]);
		$this->assertSame(['read', 'write'], $composite->getCatalog()->names());
		$this->assertSame(['warning', 'shared', 'second'], $composite->getWarnings());
	}

	public function testInvalidToolSetIsRejectedBeforeAnyExecution(): void {
		$this->expectException(InvalidArgumentException::class);
		new CompositeAgentToolSet([new \stdClass()]);
	}

	public function testDuplicateCapabilityNameIsRejectedAcrossProviders(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent tool name');
		new CompositeAgentToolSet([$this->toolSet('shared'), $this->toolSet('shared')]);
	}

	public function testUnknownToolFailsWithoutReachingProvider(): void {
		$provider = $this->createMock(IAgentToolSet::class);
		$provider->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('known')]));
		$provider->method('getWarnings')->willReturn([]);
		$provider->expects($this->never())->method('execute');
		$result = (new CompositeAgentToolSet([$provider]))->execute('call', 'unknown', ['x' => 2]);
		$this->assertFalse($result->isSuccess());
		$this->assertSame('unknown_tool', $result->getErrorCode());
	}

	public function testExecutionDelegatesToToolOwnerAndPreservesArgumentsAndMetadata(): void {
		$result = AgentToolResult::success('call-1', 'send', ['id' => 7], ['sent' => true]);
		$provider = $this->createMock(IAgentToolSet::class);
		$provider->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('send')]));
		$provider->method('getWarnings')->willReturn([]);
		$provider->expects($this->once())->method('execute')
			->with('call-1', ' send ', ['id' => 7], ['trace' => 1])->willReturn($result);
		$this->assertSame($result, (new CompositeAgentToolSet([$provider]))->execute('call-1', ' send ', ['id' => 7], ['trace' => 1]));
	}

	public function testUnknownToolCannotPrepareSuspension(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('not part of the resolved tool profiles');
		(new CompositeAgentToolSet())->prepareSuspension('call', 'missing', []);
	}

	public function testRegularToolSetDoesNotProduceSuspension(): void {
		$provider = $this->toolSet('read');
		$this->assertNull((new CompositeAgentToolSet([$provider]))->prepareSuspension('call', 'read', []));
	}

	public function testConfirmableToolReceivesPreparationAndResumeRequests(): void {
		$suspension = $this->suspension('change');
		$response = new AgentInteractionResponse('interaction', AgentInteractionResponse::DECISION_APPROVE);
		$done = AgentToolResult::success('call', 'change', ['value' => 1], ['done' => true]);
		$provider = $this->createMock(IAgentConfirmableToolSet::class);
		$provider->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability('change')]));
		$provider->method('getWarnings')->willReturn([]);
		$provider->expects($this->once())->method('prepareSuspension')
			->with('call', ' change ', ['value' => 1], ['scope' => 'test'])->willReturn($suspension);
		$provider->expects($this->once())->method('resumeSuspension')
			->with($suspension, $response, ['scope' => 'test'])->willReturn($done);
		$composite = new CompositeAgentToolSet([$provider]);
		$this->assertSame($suspension, $composite->prepareSuspension('call', ' change ', ['value' => 1], ['scope' => 'test']));
		$this->assertSame($done, $composite->resumeSuspension($suspension, $response, ['scope' => 'test']));
	}

	public function testResumeFailsWhenOriginalToolIsNoLongerConfirmable(): void {
		$composite = new CompositeAgentToolSet([$this->toolSet('change')]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('no longer available');
		$composite->resumeSuspension($this->suspension('change'), new AgentInteractionResponse('interaction', AgentInteractionResponse::DECISION_DENY));
	}

	public function testMultipleSuspensionRequestsCannotBeResumedAsOneToolCall(): void {
		$requests = [$this->interaction('first'), $this->interaction('second')];
		$suspension = new AgentSuspension('multi', AgentExecutionStatus::AWAITING_APPROVAL, $requests, [], '2026-11-03T10:00:00+00:00');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('exactly one interaction request');
		(new CompositeAgentToolSet())->resumeSuspension($suspension, new AgentInteractionResponse('first', AgentInteractionResponse::DECISION_DENY));
	}

	private function toolSet(string $name, array $warnings = []): IAgentToolSet {
		$set = $this->createStub(IAgentToolSet::class);
		$set->method('getCatalog')->willReturn(new AgentCapabilityCatalog([$this->capability($name)]));
		$set->method('getWarnings')->willReturn($warnings);
		return $set;
	}

	private function capability(string $name): AgentCapability {
		return new AgentCapability($name, $name, $name, 'test', [], 0, [
			'type' => 'function', 'function' => [
				'name' => $name, 'description' => $name,
				'parameters' => ['type' => 'object', 'properties' => [], 'required' => []]
			]
		]);
	}

	private function interaction(string $id): AgentInteractionRequest {
		return new AgentInteractionRequest(
			$id, AgentInteractionRequest::KIND_APPROVAL,
			new AgentAction('call', AgentAction::TYPE_TOOL_CALL, 'change', ['value' => 1]),
			str_repeat('a', 64), 'Confirm', 'Review this change.'
		);
	}

	private function suspension(string $tool): AgentSuspension {
		return new AgentSuspension(
			'pending', AgentExecutionStatus::AWAITING_APPROVAL,
			[$this->interaction('interaction')], [], '2026-11-03T10:00:00+00:00'
		);
	}
}
