<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 *
 * AssistantRuntime provides shared runtime composition for assistants and
 * agent implementations. Contracts remain in AssistantFoundation while
 * concrete registries, routers, sinks and configuration forms live here.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/assistantruntime
 * https://github.com/ddbase3/AssistantRuntime
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;

/**
 * Routes every execution to the runtime stored in the agent configuration.
 */
final class RoutingAgentExecutionService implements IAgentExecutionService {

	public function __construct(
		private readonly IAgentRuntimeRegistry $runtimeRegistry,
		private readonly IAgentRuntimeSelector $runtimeSelector
	) {}

	public static function getName(): string {
		return 'routingagentexecutionservice';
	}

	public function execute(
		AgentExecutionRequest $request,
		?IAgentEventSink $eventSink = null
	): AgentExecutionResult {
		$runtimeId = $this->runtimeSelector->selectRuntimeId($request->getAgentConfiguration());
		return $this->runtimeRegistry
			->getExecutionService($runtimeId)
			->execute($request, $eventSink);
	}
}
