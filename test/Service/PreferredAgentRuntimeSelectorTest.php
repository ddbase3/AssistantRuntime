<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantRuntime\Service\PreferredAgentRuntimeSelector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PreferredAgentRuntimeSelectorTest extends TestCase {

	public function testInstalledPreferredRuntimeOverridesPriority(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturnCallback(static fn(string $id): bool => $id === 'preferred');
		$registry->method('getRuntimeOptions')->willReturn([
			['id' => 'other', 'default_priority' => 100],
			['id' => 'preferred', 'default_priority' => 1]
		]);
		$selector = new PreferredAgentRuntimeSelector($registry, ' PREFERRED! ');
		$this->assertSame('preferred', $selector->getDefaultRuntimeId());
		$this->assertSame('preferred', $selector->selectRuntimeId([]));
	}

	public function testExplicitRuntimeOverridesPreferredRuntime(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturn(true);
		$selector = new PreferredAgentRuntimeSelector($registry, 'preferred');
		$this->assertSame('explicit', $selector->selectRuntimeId(['agent_runtime' => 'EXPLICIT']));
	}

	public function testUnavailablePreferredRuntimeUsesStrictUniquePriority(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturn(false);
		$registry->method('getRuntimeOptions')->willReturn([
			['id' => 'chosen', 'default_priority' => 10],
			['id' => 'other', 'default_priority' => 1]
		]);
		$this->assertSame('chosen', (new PreferredAgentRuntimeSelector($registry, 'missing'))->getDefaultRuntimeId());
	}

	public function testUnavailablePreferredRuntimeDoesNotHideAmbiguousInstallation(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturn(false);
		$registry->method('getRuntimeOptions')->willReturn([
			['id' => 'a', 'default_priority' => 0],
			['id' => 'b', 'default_priority' => 0]
		]);
		$this->expectException(RuntimeException::class);
		(new PreferredAgentRuntimeSelector($registry, 'NOT FOUND'))->getDefaultRuntimeId();
	}
}
