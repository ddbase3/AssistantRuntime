<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantRuntime\Service\AgentEventDispatcher;
use AssistantRuntime\Service\CollectingAgentEventSink;
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
