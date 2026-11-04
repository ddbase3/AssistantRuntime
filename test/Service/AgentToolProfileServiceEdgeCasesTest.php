<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentToolProfileProvider;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantRuntime\Service\AgentToolProfileService;
use AssistantRuntime\Test\Service\Fixtures\OtherToolProfileFixture;
use Base3\Api\IClassMap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AgentToolProfileServiceEdgeCasesTest extends TestCase {

	public function testEmptyProfileSelectionDoesNotDiscoverProviders(): void {
		$classMap = $this->createMock(IClassMap::class);
		$classMap->expects($this->never())->method('getInstancesByInterface');
		$set = (new AgentToolProfileService($classMap))->resolve(['', ' !!! ', null, []], new AgentExecutionRequest([]));
		$this->assertSame([], $set->getCatalog()->names());
	}

	public function testValidProfilesAreNormalizedDeduplicatedAndResolvedTogether(): void {
		$service = $this->service([$this->provider()]);
		$set = $service->resolve([' FIRST! ', 'first', 'second', ' SECOND ', false, []], new AgentExecutionRequest([]));
		$this->assertSame(['first_action', 'second_action'], $set->getCatalog()->names());
		$this->assertSame(['first', 'second'], array_column($service->getOptions(), 'id'));
		$this->assertTrue($service->hasProfile('  FIRST! '));
		$this->assertFalse($service->hasProfile('missing'));
	}

	public function testUnknownSelectedProfileIsRejectedBeforeExecution(): void {
		$service = $this->service([$this->provider()]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unknown agent tool profile: missing');
		$service->resolve(['missing'], new AgentExecutionRequest([]));
	}

	public function testUnrelatedClassMapObjectsAreIgnored(): void {
		$service = $this->service([new \stdClass(), $this->provider()]);
		$this->assertCount(2, $service->getOptions());
	}

	public function testDuplicateProviderIdsAreRejected(): void {
		$provider = $this->provider();
		$service = $this->service([$provider, $provider]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent tool profile provider: test');
		$service->getOptions();
	}

	public function testDuplicateProfileIdsAcrossProvidersAreRejected(): void {
		$service = $this->service([$this->provider(), new OtherToolProfileFixture()]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent tool profile id: first');
		$service->getOptions();
	}

	private function provider(): AgentToolProfileServiceTestDouble1 {
		return new AgentToolProfileServiceTestDouble1(
			new AgentToolProfileServiceTestDouble2($this->capability('first_action')),
			new AgentToolProfileServiceTestDouble2($this->capability('second_action'))
		);
	}

	private function capability(string $name): AgentCapability {
		return new AgentCapability($name, $name, $name, 'test', [], 0, [
			'type' => 'function', 'function' => ['name' => $name, 'parameters' => ['type' => 'object']]
		]);
	}

	private function service(array $providers): AgentToolProfileService {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->with(IAgentToolProfileProvider::class)->willReturn($providers);
		return new AgentToolProfileService($classMap);
	}
}

require_once __DIR__ . '/AgentToolProfileServiceTestDouble1.php';
require_once __DIR__ . '/AgentToolProfileServiceTestDouble2.php';
