<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentConversationRuntimeService;
use AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use AssistantFoundation\Api\IAgentRuntimeService;
use AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use AssistantRuntime\Service\AgentRuntimeRegistry;
use AssistantRuntime\Test\Service\Fixtures\AlphaRuntimeConversationFixture;
use AssistantRuntime\Test\Service\Fixtures\AlphaRuntimeExecutionFixture;
use AssistantRuntime\Test\Service\Fixtures\AlphaRuntimeFormFixture;
use AssistantRuntime\Test\Service\Fixtures\AlphaRuntimeTextTaskFixture;
use AssistantRuntime\Test\Service\Fixtures\BetaRuntimeExecutionFixture;
use AssistantRuntime\Test\Service\Fixtures\BetaRuntimeFormFixture;
use Base3\Api\IClassMap;
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
