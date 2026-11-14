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

use Base3\AssistantFoundation\Api\IAgentConversationRuntimeService;
use Base3\AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use Base3\AssistantFoundation\Api\IAgentRuntimeService;
use Base3\AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use Base3\AssistantRuntime\Service\AgentRuntimeRegistry;
use Base3\AssistantRuntime\Test\Service\Fixtures\AlphaRuntimeConversationFixture;
use Base3\AssistantRuntime\Test\Service\Fixtures\AlphaRuntimeExecutionFixture;
use Base3\AssistantRuntime\Test\Service\Fixtures\AlphaRuntimeFormFixture;
use Base3\AssistantRuntime\Test\Service\Fixtures\AlphaRuntimeTextTaskFixture;
use Base3\AssistantRuntime\Test\Service\Fixtures\BetaRuntimeExecutionFixture;
use Base3\AssistantRuntime\Test\Service\Fixtures\BetaRuntimeFormFixture;
use Base3\CoreFoundation\Api\IClassMap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AgentRuntimeRegistryIsolationTest extends TestCase {

	public function testOptionalServicesCannotCreateImplicitRuntimeWithoutExecutionAndForm(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new BetaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new BetaRuntimeFormFixture()],
			IAgentConversationRuntimeService::class => [new AlphaRuntimeConversationFixture()],
			IAgentTextTaskRuntimeService::class => [new AlphaRuntimeTextTaskFixture()]
		]);
		$this->assertSame(['beta'], $registry->getRuntimeIds());
		$this->assertSame('beta', $registry->getRuntimeOptions()[0]['id']);
		$this->assertFalse($registry->getRuntimeOptions()[0]['conversation_service']);
		$this->assertFalse($registry->getRuntimeOptions()[0]['text_task_service']);
		$this->assertFalse($registry->hasRuntime('alpha'));
	}

	public function testOptionalConversationLookupForUnregisteredRuntimeFailsClosed(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new BetaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new BetaRuntimeFormFixture()],
			IAgentConversationRuntimeService::class => [new AlphaRuntimeConversationFixture()]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unknown agent runtime: alpha');
		$registry->getConversationService('alpha');
	}

	public function testOptionalTextTaskLookupForUnregisteredRuntimeFailsClosed(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new BetaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new BetaRuntimeFormFixture()],
			IAgentTextTaskRuntimeService::class => [new AlphaRuntimeTextTaskFixture()]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unknown agent runtime: alpha');
		$registry->getTextTaskService('alpha');
	}

	public function testMissingConfigurationLookupDoesNotReturnUnrelatedRuntimeForm(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture()]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unknown agent runtime: missing');
		$registry->getConfigFormService('MISSING!');
	}

	private function registry(array $available): AgentRuntimeRegistry {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturnCallback(
			static fn(string $interface): array => $available[$interface] ?? []
		);
		return new AgentRuntimeRegistry($map);
	}
}
require_once __DIR__ . '/Fixtures/AlphaRuntimeExecutionFixture.php';
require_once __DIR__ . '/Fixtures/AlphaRuntimeFormFixture.php';
require_once __DIR__ . '/Fixtures/AlphaRuntimeConversationFixture.php';
require_once __DIR__ . '/Fixtures/AlphaRuntimeTextTaskFixture.php';
require_once __DIR__ . '/Fixtures/BetaRuntimeExecutionFixture.php';
require_once __DIR__ . '/Fixtures/BetaRuntimeFormFixture.php';
