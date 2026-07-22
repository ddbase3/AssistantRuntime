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
use AssistantFoundation\Dto\AgentExecutionEvent;

/**
 * Collects incremental events for non-streaming callers and diagnostics.
 */
final class CollectingAgentEventSink implements IAgentEventSink {

	/** @var array<int,AgentExecutionEvent> */
	private array $events = [];

	public function emit(AgentExecutionEvent $event): void {
		$this->events[] = $event;
	}

	public function isCancelled(): bool {
		return false;
	}

	/** @return array<int,AgentExecutionEvent> */
	public function getEvents(): array {
		return $this->events;
	}
}
