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
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->with(IAgentEventSink::CONTEXT_KEY)->willReturn($sink);

		$this->assertSame($sink, AgentEventDispatcher::fromContext($context));
	}
}
