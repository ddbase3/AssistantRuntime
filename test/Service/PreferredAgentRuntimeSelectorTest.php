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

use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\AssistantRuntime\Service\PreferredAgentRuntimeSelector;
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
