<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentConversationRuntimeService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use AssistantFoundation\Dto\AgentConversationRequest;
use AssistantFoundation\Dto\AgentConversationState;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AgentTextTaskResult;
use AssistantRuntime\Service\RoutingAgentConversationService;
use AssistantRuntime\Service\RoutingAgentTextTaskService;
use PHPUnit\Framework\TestCase;

final class RoutingAgentServicesTest extends TestCase {

	public function testConversationRequestUsesSelectedRuntime(): void {
		$selector = $this->createStub(IAgentRuntimeSelector::class);
		$selector->method('selectRuntimeId')->willReturn('missionbay');
		$runtime = $this->createMock(IAgentConversationRuntimeService::class);
		$runtime->expects($this->once())
			->method('getState')
			->willReturn(new AgentConversationState([], null, [], 'assistant'));
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('getConversationService')->with('missionbay')->willReturn($runtime);

		$service = new RoutingAgentConversationService($registry, $selector);
		$this->assertSame([], $service->getState(new AgentConversationRequest([]))->getConversations());
	}

	public function testTextTaskUsesSelectedRuntime(): void {
		$selector = $this->createStub(IAgentRuntimeSelector::class);
		$selector->method('selectRuntimeId')->willReturn('missionbay');
		$runtime = $this->createMock(IAgentTextTaskRuntimeService::class);
		$runtime->expects($this->once())
			->method('executeTextTask')
			->willReturn(new AgentTextTaskResult('Result'));
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('getTextTaskService')->with('missionbay')->willReturn($runtime);

		$service = new RoutingAgentTextTaskService($registry, $selector);
		$result = $service->executeTextTask(new AgentTextTaskRequest([], 'title', '', 'Create a title.'));

		$this->assertSame('Result', $result->getContent());
	}
}
