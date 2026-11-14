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

namespace Base3\AssistantRuntime\Test\Service;

use Base3\AssistantFoundation\Dto\AgentExecutionEvent;
use Base3\AssistantRuntime\Service\CollectingAgentEventSink;
use PHPUnit\Framework\TestCase;

final class CollectingAgentEventSinkTest extends TestCase {

	public function testCollectsEventsInEmissionOrder(): void {
		$sink = new CollectingAgentEventSink();
		$first = new AgentExecutionEvent('token', ['text' => 'A']);
		$second = new AgentExecutionEvent('done', ['status' => 'complete']);

		$sink->emit($first);
		$sink->emit($second);

		$this->assertSame([$first, $second], $sink->getEvents());
		$this->assertFalse($sink->isCancelled());
	}
}
