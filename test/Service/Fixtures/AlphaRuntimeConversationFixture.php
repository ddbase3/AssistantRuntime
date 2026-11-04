<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

use AssistantFoundation\Api\IAgentConversationRuntimeService;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationRequest;
use AssistantFoundation\Dto\AgentConversationState;

final class AlphaRuntimeConversationFixture implements IAgentConversationRuntimeService {

	public static function getName(): string { return 'alpharuntimeconversationfixture'; }
	public static function getRuntimeId(): string { return ' ALPHA '; }
	public function getState(AgentConversationRequest $request, string $conversationId = ''): AgentConversationState { return $this->state(); }
	public function createConversation(AgentConversationRequest $request, ?string $conversationId = null, string $title = '', string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY, string $openingMessage = ''): AgentConversationState { return $this->state(); }
	public function activateConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState { return $this->state(); }
	public function renameConversation(AgentConversationRequest $request, string $conversationId, string $title, string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL): AgentConversationState { return $this->state(); }
	public function deleteConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState { return $this->state(); }
	public function appendMessage(AgentConversationRequest $request, string $conversationId, array $message): AgentConversationState { return $this->state(); }
	public function touchConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState { return $this->state(); }

	private function state(): AgentConversationState { return new AgentConversationState([], null, [], 'alpha'); }
}
