<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentRuntimeService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;

class AlphaRuntimeExecutionFixture implements IAgentRuntimeService {

	public static function getName(): string { return 'alpharuntimeexecutionfixture'; }
	public static function getRuntimeId(): string { return ' ALPHA '; }
	public static function getRuntimeLabel(): string { return ' Zulu '; }
	public static function getRuntimeDescription(): string { return ' Alpha description '; }
	public static function getDefaultPriority(): int { return 10; }

	public function execute(AgentExecutionRequest $request, ?IAgentEventSink $eventSink = null): AgentExecutionResult {
		return new AgentExecutionResult(['configuration' => $request->getAgentConfiguration()]);
	}
}
