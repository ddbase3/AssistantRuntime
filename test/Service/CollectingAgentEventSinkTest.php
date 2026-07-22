<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantRuntime\Service\CollectingAgentEventSink;
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
