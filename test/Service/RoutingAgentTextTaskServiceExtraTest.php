<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AgentTextTaskResult;
use AssistantRuntime\Service\RoutingAgentTextTaskService;
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
