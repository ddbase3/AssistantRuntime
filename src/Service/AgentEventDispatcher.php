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

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;

/**
 * Small shared helper for resolving and writing the run-scoped event sink.
 */
final class AgentEventDispatcher {

	public static function fromContext(IAgentContext $context): ?IAgentEventSink {
		$eventSink = $context->getVar(IAgentEventSink::CONTEXT_KEY);
		return $eventSink instanceof IAgentEventSink ? $eventSink : null;
	}

	/** @param array<string,mixed> $payload */
	public static function emit(?IAgentEventSink $eventSink, string $event, array $payload): void {
		if ($eventSink === null || $eventSink->isCancelled()) {
			return;
		}
		$eventSink->emit(new AgentExecutionEvent($event, $payload));
	}
}
