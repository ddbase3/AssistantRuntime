<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentConversationRuntimeService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationRequest;
use AssistantFoundation\Dto\AgentConversationState;
use AssistantRuntime\Service\RoutingAgentConversationService;
use PHPUnit\Framework\TestCase;

final class RoutingAgentConversationServiceDelegationTest extends TestCase {

	public function testEveryConversationOperationPassesArgumentsToTheChosenRuntime(): void {
		$request = new AgentConversationRequest(['agent_runtime' => 'alpha'], ['actor' => 'user'], 'node');
		$state = new AgentConversationState([], null, [['role' => 'assistant', 'content' => 'hello']], 'node');
		$runtime = $this->createMock(IAgentConversationRuntimeService::class);
		$runtime->expects($this->once())->method('getState')->with($request, 'conversation-1')->willReturn($state);
		$runtime->expects($this->once())->method('createConversation')->with($request, 'conversation-2', 'Hello', AgentConversation::TITLE_SOURCE_MANUAL, 'start')->willReturn($state);
		$runtime->expects($this->once())->method('activateConversation')->with($request, 'conversation-3')->willReturn($state);
		$runtime->expects($this->once())->method('renameConversation')->with($request, 'conversation-4', 'New name', AgentConversation::TITLE_SOURCE_AUTOMATIC)->willReturn($state);
		$runtime->expects($this->once())->method('deleteConversation')->with($request, 'conversation-5')->willReturn($state);
		$runtime->expects($this->once())->method('appendMessage')->with($request, 'conversation-6', ['role' => 'user', 'content' => 'message'])->willReturn($state);
		$runtime->expects($this->once())->method('touchConversation')->with($request, 'conversation-7')->willReturn($state);
		$selector = $this->createMock(IAgentRuntimeSelector::class);
		$selector->expects($this->exactly(7))->method('selectRuntimeId')->with(['agent_runtime' => 'alpha'])->willReturn('alpha');
		$registry = $this->createMock(IAgentRuntimeRegistry::class);
		$registry->expects($this->exactly(7))->method('getConversationService')->with('alpha')->willReturn($runtime);
		$service = new RoutingAgentConversationService($registry, $selector);
		$this->assertSame($state, $service->getState($request, 'conversation-1'));
		$this->assertSame($state, $service->createConversation($request, 'conversation-2', 'Hello', AgentConversation::TITLE_SOURCE_MANUAL, 'start'));
		$this->assertSame($state, $service->activateConversation($request, 'conversation-3'));
		$this->assertSame($state, $service->renameConversation($request, 'conversation-4', 'New name', AgentConversation::TITLE_SOURCE_AUTOMATIC));
		$this->assertSame($state, $service->deleteConversation($request, 'conversation-5'));
		$this->assertSame($state, $service->appendMessage($request, 'conversation-6', ['role' => 'user', 'content' => 'message']));
		$this->assertSame($state, $service->touchConversation($request, 'conversation-7'));
	}

	public function testDefaultConversationArgumentsUseFoundationDefaults(): void {
		$request = new AgentConversationRequest([]);
		$state = new AgentConversationState([], null, [], 'node');
		$runtime = $this->createMock(IAgentConversationRuntimeService::class);
		$runtime->expects($this->once())->method('createConversation')
			->with($request, null, '', AgentConversation::TITLE_SOURCE_TEMPORARY, '')->willReturn($state);
		$selector = $this->createStub(IAgentRuntimeSelector::class);
		$selector->method('selectRuntimeId')->willReturn('alpha');
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('getConversationService')->willReturn($runtime);
		$this->assertSame($state, (new RoutingAgentConversationService($registry, $selector))->createConversation($request));
	}
}
