<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantFoundation\Api\IAgentRuntimeService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;
use AssistantRuntime\Service\RoutingAgentExecutionService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoutingAgentExecutionServiceTest extends TestCase {

	public function testRequestAndEventSinkArePassedToSelectedExecutionService(): void {
		$request = new AgentExecutionRequest(['agent_runtime' => 'alpha'], ['prompt' => 'hello'], ['actor' => 'user']);
		$sink = $this->createStub(IAgentEventSink::class);
		$result = new AgentExecutionResult(['message' => 'done'], ['one warning']);
		$execution = $this->createMock(IAgentRuntimeService::class);
		$execution->expects($this->once())->method('execute')->with($request, $sink)->willReturn($result);
		$selector = $this->createMock(IAgentRuntimeSelector::class);
		$selector->expects($this->once())->method('selectRuntimeId')->with(['agent_runtime' => 'alpha'])->willReturn('alpha');
		$registry = $this->createMock(IAgentRuntimeRegistry::class);
		$registry->expects($this->once())->method('getExecutionService')->with('alpha')->willReturn($execution);
		$this->assertSame($result, (new RoutingAgentExecutionService($registry, $selector))->execute($request, $sink));
	}

	public function testRuntimeExceptionPropagatesWithoutSelectingAnotherRuntime(): void {
		$registry = $this->createMock(IAgentRuntimeRegistry::class);
		$registry->expects($this->once())->method('getExecutionService')->with('missing')
			->willThrowException(new RuntimeException('Runtime is not installed.'));
		$selector = $this->createStub(IAgentRuntimeSelector::class);
		$selector->method('selectRuntimeId')->willReturn('missing');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Runtime is not installed.');
		(new RoutingAgentExecutionService($registry, $selector))->execute(new AgentExecutionRequest([]));
	}
}
