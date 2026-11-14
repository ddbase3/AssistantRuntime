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

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentRuntimeService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;

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
