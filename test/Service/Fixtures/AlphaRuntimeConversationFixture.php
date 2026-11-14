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

namespace Base3\AssistantRuntime\Test\Service\Fixtures;

use Base3\AssistantFoundation\Api\IAgentConversationRuntimeService;
use Base3\AssistantFoundation\Dto\AgentConversation;
use Base3\AssistantFoundation\Dto\AgentConversationRequest;
use Base3\AssistantFoundation\Dto\AgentConversationState;

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
