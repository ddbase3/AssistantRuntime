<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantRuntime\Service\StrictAgentRuntimeSelector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StrictAgentRuntimeSelectorTest extends TestCase {

	public function testExplicitRuntimeIdWinsAndIsNormalized(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturnCallback(static fn(string $id): bool => $id === 'runtime-1');
		$selector = new StrictAgentRuntimeSelector($registry);
		$this->assertSame('runtime-1', $selector->selectRuntimeId(['agent_runtime' => ' Runtime-1! ']));
	}

	public function testUnknownExplicitRuntimeFailsRatherThanSelectingAnother(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturn(false);
		$selector = new StrictAgentRuntimeSelector($registry);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Configured agent runtime does not exist: bad');
		$selector->selectRuntimeId(['agent_runtime' => ' BAD ']);
	}

	public function testSingleRuntimeIsDefaultRegardlessOfPriority(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('getRuntimeOptions')->willReturn([['id' => 'sole', 'default_priority' => -8]]);
		$selector = new StrictAgentRuntimeSelector($registry);
		$this->assertSame('sole', $selector->getDefaultRuntimeId());
		$this->assertSame('sole', $selector->selectRuntimeId([]));
		$this->assertSame('sole', $selector->selectRuntimeId(['agent_runtime' => ' !!! ']));
	}

	public function testHighestUniquePriorityIsChosenWithoutInsertionOrderBias(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('getRuntimeOptions')->willReturn([
			['id' => 'lowest', 'default_priority' => 5],
			['id' => 'highest', 'default_priority' => 30],
			['id' => 'middle']
		]);
		$this->assertSame('highest', (new StrictAgentRuntimeSelector($registry))->getDefaultRuntimeId());
	}

	public function testMissingInstalledRuntimeIsAnError(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('getRuntimeOptions')->willReturn([]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('No agent runtime is installed.');
		(new StrictAgentRuntimeSelector($registry))->getDefaultRuntimeId();
	}

	public function testEqualHighestPrioritiesRequireExplicitSelection(): void {
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('getRuntimeOptions')->willReturn([
			['id' => 'a', 'default_priority' => 8],
			['id' => 'b', 'default_priority' => 8],
			['id' => 'c', 'default_priority' => 0]
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Store agent_runtime explicitly');
		(new StrictAgentRuntimeSelector($registry))->selectRuntimeId([]);
	}
}
