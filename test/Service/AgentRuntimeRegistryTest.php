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
use AssistantRuntime\Test\Service\Fixtures\CollisionRuntimeExecutionFixture;
use AssistantRuntime\Test\Service\Fixtures\EmptyIdRuntimeExecutionFixture;
use Base3\Api\IClassMap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AgentRuntimeRegistryTest extends TestCase {

	public function testPairedRuntimeRegistryNormalizesIdsAndSortsRuntimeOptions(): void {
		$alpha = new AlphaRuntimeExecutionFixture();
		$beta = new BetaRuntimeExecutionFixture();
		$alphaForm = new AlphaRuntimeFormFixture();
		$betaForm = new BetaRuntimeFormFixture();
		$conversation = new AlphaRuntimeConversationFixture();
		$textTask = new AlphaRuntimeTextTaskFixture();
		$registry = $this->registry([
			IAgentRuntimeService::class => [$alpha, $beta],
			IAgentRuntimeConfigFormService::class => [$alphaForm, $betaForm],
			IAgentConversationRuntimeService::class => [$conversation],
			IAgentTextTaskRuntimeService::class => [$textTask]
		]);

		$this->assertSame(['alpha', 'beta'], $registry->getRuntimeIds());
		$this->assertTrue($registry->hasRuntime('  ALPHA! '));
		$this->assertFalse($registry->hasRuntime('missing'));
		$this->assertFalse($registry->hasRuntime('!!!'));
		$this->assertSame($alpha, $registry->getExecutionService(' ALPHA '));
		$this->assertSame($betaForm, $registry->getConfigFormService(' beta '));
		$this->assertSame($conversation, $registry->getConversationService('alpha'));
		$this->assertSame($textTask, $registry->getTextTaskService('alpha'));
		$this->assertSame([
			[
				'id' => 'beta', 'label' => 'Alpha', 'description' => 'Beta description',
				'default_priority' => 20, 'conversation_service' => false, 'text_task_service' => false
			],
			[
				'id' => 'alpha', 'label' => 'Zulu', 'description' => 'Alpha description',
				'default_priority' => 10, 'conversation_service' => true, 'text_task_service' => true
			]
		], $registry->getRuntimeOptions());
	}

	public function testRegistryCanBeEmptyAndIgnoresNonRuntimeClassMapResults(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new \stdClass()],
			IAgentRuntimeConfigFormService::class => [new \stdClass()]
		]);
		$this->assertSame([], $registry->getRuntimeIds());
		$this->assertSame([], $registry->getRuntimeOptions());
	}

	public function testPairedExecutionWithoutConfigurationServiceFailsClosed(): void {
		$registry = $this->registry([IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()]]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('no configuration form service: alpha');
		$registry->getRuntimeIds();
	}

	public function testPairedConfigurationWithoutExecutionServiceFailsClosed(): void {
		$registry = $this->registry([IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture()]]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('no execution service: alpha');
		$registry->getRuntimeIds();
	}

	public function testDuplicateExecutionIdsAreRejectedEvenAcrossDifferentClasses(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture(), new CollisionRuntimeExecutionFixture()]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent runtime execution service: alpha');
		$registry->getRuntimeIds();
	}

	public function testDuplicateConfigurationServiceIdsAreRejected(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture(), new AlphaRuntimeFormFixture()]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent runtime configuration form service: alpha');
		$registry->getRuntimeIds();
	}

	public function testEmptyRuntimeIdIsRejectedDuringDiscovery(): void {
		$registry = $this->registry([IAgentRuntimeService::class => [new EmptyIdRuntimeExecutionFixture()]]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('empty runtime id');
		$registry->getRuntimeIds();
	}

	public function testMissingAndEmptyRuntimeLookupAreRejected(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture()]
		]);
		foreach (['  OTHER ', '  !!! '] as $id) {
			try {
				$registry->getExecutionService($id);
				$this->fail('Unknown runtime must be rejected.');
			} catch (RuntimeException $e) {
				$this->assertStringContainsString('Unknown agent runtime', $e->getMessage());
			}
		}
	}

	public function testOptionalConversationServiceIsRequiredOnlyWhenRequested(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture()]
		]);
		$this->assertTrue($registry->hasRuntime('alpha'));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('does not provide conversation access: alpha');
		$registry->getConversationService('alpha');
	}

	public function testOptionalTextTaskServiceIsRequiredOnlyWhenRequested(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture()]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('does not provide isolated text tasks: alpha');
		$registry->getTextTaskService('alpha');
	}

	public function testDuplicateOptionalConversationServicesAreRejected(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture()],
			IAgentConversationRuntimeService::class => [new AlphaRuntimeConversationFixture(), new AlphaRuntimeConversationFixture()]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent runtime conversation service: alpha');
		$registry->getRuntimeOptions();
	}

	public function testDuplicateOptionalTextTaskServicesAreRejected(): void {
		$registry = $this->registry([
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture()],
			IAgentTextTaskRuntimeService::class => [new AlphaRuntimeTextTaskFixture(), new AlphaRuntimeTextTaskFixture()]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent runtime text-task service: alpha');
		$registry->getRuntimeOptions();
	}

	public function testDiscoveredRuntimeInstancesAreCachedAfterFirstLookup(): void {
		$counts = [];
		$available = [
			IAgentRuntimeService::class => [new AlphaRuntimeExecutionFixture()],
			IAgentRuntimeConfigFormService::class => [new AlphaRuntimeFormFixture()]
		];
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturnCallback(
			static function(string $interface) use ($available, &$counts): array {
				$counts[$interface] = ($counts[$interface] ?? 0) + 1;
				return $available[$interface] ?? [];
			}
		);
		$registry = new AgentRuntimeRegistry($classMap);
		$registry->getRuntimeOptions();
		$registry->hasRuntime('alpha');
		$registry->getExecutionService('alpha');
		$registry->getRuntimeIds();
		$this->assertSame(1, $counts[IAgentRuntimeService::class]);
		$this->assertSame(1, $counts[IAgentRuntimeConfigFormService::class]);
		$this->assertSame(1, $counts[IAgentConversationRuntimeService::class]);
		$this->assertSame(1, $counts[IAgentTextTaskRuntimeService::class]);
	}

	private function registry(array $available): AgentRuntimeRegistry {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturnCallback(
			static fn(string $interface): array => $available[$interface] ?? []
		);
		return new AgentRuntimeRegistry($classMap);
	}
}
