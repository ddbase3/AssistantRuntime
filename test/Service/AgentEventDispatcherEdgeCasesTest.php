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

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Dto\AgentExecutionEvent;
use Base3\AssistantRuntime\Service\AgentEventDispatcher;
use Base3\AssistantRuntime\Service\CollectingAgentEventSink;
use PHPUnit\Framework\TestCase;

final class AgentEventDispatcherEdgeCasesTest extends TestCase {

	public function testMissingOrMalformedContextSinkReturnsNull(): void {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturn('not-a-sink');
		$this->assertNull(AgentEventDispatcher::fromContext($context));
	}

	public function testNullSinkDoesNotEmitEvents(): void {
		AgentEventDispatcher::emit(null, 'token', ['text' => 'discarded']);
		$this->assertTrue(true);
	}

	public function testCancelledSinkDoesNotReceiveNewEvents(): void {
		$sink = $this->createMock(IAgentEventSink::class);
		$sink->expects($this->once())->method('isCancelled')->willReturn(true);
		$sink->expects($this->never())->method('emit');
		AgentEventDispatcher::emit($sink, 'token', ['text' => 'discarded']);
	}

	public function testCollectingSinkPreservesEventOrderingAndPayload(): void {
		$sink = new CollectingAgentEventSink();
		AgentEventDispatcher::emit($sink, 'tool.started', ['id' => 'call-1']);
		AgentEventDispatcher::emit($sink, 'tool.finished', ['success' => true]);
		$this->assertFalse($sink->isCancelled());
		$this->assertCount(2, $sink->getEvents());
		$this->assertSame('tool.started', $sink->getEvents()[0]->getName());
		$this->assertSame(['success' => true], $sink->getEvents()[1]->getPayload());
	}
}
