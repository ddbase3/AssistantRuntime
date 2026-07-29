<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantFoundation\Api\IAgentTextTaskService;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AgentTextTaskResult;

final class RoutingAgentTextTaskService implements IAgentTextTaskService {

	public function __construct(
		private readonly IAgentRuntimeRegistry $runtimeRegistry,
		private readonly IAgentRuntimeSelector $runtimeSelector
	) {}

	public static function getName(): string {
		return 'routingagenttexttaskservice';
	}

	public function executeTextTask(AgentTextTaskRequest $request): AgentTextTaskResult {
		$runtimeId = $this->runtimeSelector->selectRuntimeId($request->getAgentConfiguration());
		return $this->runtimeRegistry
			->getTextTaskService($runtimeId)
			->executeTextTask($request);
	}
}
