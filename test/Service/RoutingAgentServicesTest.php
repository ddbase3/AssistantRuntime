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

use Base3\AssistantFoundation\Api\IAgentConversationRuntimeService;
use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\AssistantFoundation\Api\IAgentRuntimeSelector;
use Base3\AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use Base3\AssistantFoundation\Dto\AgentConversationRequest;
use Base3\AssistantFoundation\Dto\AgentConversationState;
use Base3\AssistantFoundation\Dto\AgentTextTaskRequest;
use Base3\AssistantFoundation\Dto\AgentTextTaskResult;
use Base3\AssistantRuntime\Service\RoutingAgentConversationService;
use Base3\AssistantRuntime\Service\RoutingAgentTextTaskService;
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
