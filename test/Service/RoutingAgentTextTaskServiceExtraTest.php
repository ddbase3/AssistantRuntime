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

use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\AssistantFoundation\Api\IAgentRuntimeSelector;
use Base3\AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use Base3\AssistantFoundation\Dto\AgentTextTaskRequest;
use Base3\AssistantFoundation\Dto\AgentTextTaskResult;
use Base3\AssistantRuntime\Service\RoutingAgentTextTaskService;
use PHPUnit\Framework\TestCase;

final class RoutingAgentTextTaskServiceExtraTest extends TestCase {

	public function testTextTaskKeepsRequestContextAndReturnsOriginalResult(): void {
		$request = new AgentTextTaskRequest(
			['agent_runtime' => 'beta'], 'summary', 'Summarize faithfully.', 'A document',
			['actor' => 'test'], true, false
		);
		$result = new AgentTextTaskResult('Summary', ['truncated'], ['tokens' => 40]);
		$runtime = $this->createMock(IAgentTextTaskRuntimeService::class);
		$runtime->expects($this->once())->method('executeTextTask')->with($request)->willReturn($result);
		$selector = $this->createMock(IAgentRuntimeSelector::class);
		$selector->expects($this->once())->method('selectRuntimeId')->with(['agent_runtime' => 'beta'])->willReturn('beta');
		$registry = $this->createMock(IAgentRuntimeRegistry::class);
		$registry->expects($this->once())->method('getTextTaskService')->with('beta')->willReturn($runtime);
		$this->assertSame($result, (new RoutingAgentTextTaskService($registry, $selector))->executeTextTask($request));
	}
}
