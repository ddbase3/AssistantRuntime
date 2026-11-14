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

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\AssistantFoundation\Api\IAgentRuntimeSelector;
use Base3\AssistantFoundation\Api\IAgentRuntimeService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;
use Base3\AssistantRuntime\Service\RoutingAgentExecutionService;
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
