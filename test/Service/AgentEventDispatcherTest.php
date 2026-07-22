<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantRuntime\Service\AgentEventDispatcher;
use PHPUnit\Framework\TestCase;

final class AgentEventDispatcherTest extends TestCase {

	public function testEmitCreatesEventForActiveSink(): void {
		$sink = $this->createMock(IAgentEventSink::class);
		$sink->method('isCancelled')->willReturn(false);
		$sink->expects($this->once())
			->method('emit')
			->with($this->callback(static function(AgentExecutionEvent $event): bool {
				return $event->getName() === 'token'
					&& $event->getPayload() === ['text' => 'Hello'];
			}));

		AgentEventDispatcher::emit($sink, 'token', ['text' => 'Hello']);
	}

	public function testFromContextReturnsOnlyEventSinkInstances(): void {
		$sink = $this->createStub(IAgentEventSink::class);
		$context = $this->createMock(IAgentContext::class);
		$context->method('getVar')->with(IAgentEventSink::CONTEXT_KEY)->willReturn($sink);

		$this->assertSame($sink, AgentEventDispatcher::fromContext($context));
	}
}
