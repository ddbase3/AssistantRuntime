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

namespace Base3\AssistantRuntime\Service;

use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\AssistantFoundation\Api\IAgentRuntimeSelector;
use Base3\AssistantFoundation\Api\IAgentTextTaskService;
use Base3\AssistantFoundation\Dto\AgentTextTaskRequest;
use Base3\AssistantFoundation\Dto\AgentTextTaskResult;

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
