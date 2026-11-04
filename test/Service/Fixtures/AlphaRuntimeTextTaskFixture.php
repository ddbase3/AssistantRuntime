<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

use AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AgentTextTaskResult;

final class AlphaRuntimeTextTaskFixture implements IAgentTextTaskRuntimeService {

	public static function getName(): string { return 'alpharuntimetexttaskfixture'; }
	public static function getRuntimeId(): string { return ' ALPHA '; }
	public function executeTextTask(AgentTextTaskRequest $request): AgentTextTaskResult { return new AgentTextTaskResult($request->getPrompt()); }
}
