<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentConversationRuntimeService;
use AssistantFoundation\Api\IAgentConversationService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationRequest;
use AssistantFoundation\Dto\AgentConversationState;

final class RoutingAgentConversationService implements IAgentConversationService {

	public function __construct(
		private readonly IAgentRuntimeRegistry $runtimeRegistry,
		private readonly IAgentRuntimeSelector $runtimeSelector
	) {}

	public static function getName(): string {
		return 'routingagentconversationservice';
	}

	public function getState(AgentConversationRequest $request, string $conversationId = ''): AgentConversationState {
		return $this->service($request)->getState($request, $conversationId);
	}

	public function createConversation(
		AgentConversationRequest $request,
		?string $conversationId = null,
		string $title = '',
		string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY,
		string $openingMessage = ''
	): AgentConversationState {
		return $this->service($request)->createConversation(
			$request,
			$conversationId,
			$title,
			$titleSource,
			$openingMessage
		);
	}

	public function activateConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		return $this->service($request)->activateConversation($request, $conversationId);
	}

	public function renameConversation(
		AgentConversationRequest $request,
		string $conversationId,
		string $title,
		string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL
	): AgentConversationState {
		return $this->service($request)->renameConversation($request, $conversationId, $title, $titleSource);
	}

	public function deleteConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		return $this->service($request)->deleteConversation($request, $conversationId);
	}

	public function appendMessage(
		AgentConversationRequest $request,
		string $conversationId,
		array $message
	): AgentConversationState {
		return $this->service($request)->appendMessage($request, $conversationId, $message);
	}

	public function touchConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		return $this->service($request)->touchConversation($request, $conversationId);
	}

	private function service(AgentConversationRequest $request): IAgentConversationRuntimeService {
		$runtimeId = $this->runtimeSelector->selectRuntimeId($request->getAgentConfiguration());
		return $this->runtimeRegistry->getConversationService($runtimeId);
	}
}
