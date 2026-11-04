<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentContextProfileProvider;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantRuntime\Service\AgentContextProfileService;
use AssistantRuntime\Test\Service\Fixtures\OtherContextProfileFixture;
use Base3\Api\IClassMap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AgentContextProfileServiceEdgeCasesTest extends TestCase {

	public function testMissingProfileReturnsFalseAndBuildThrows(): void {
		$service = $this->service([new AgentContextProfileServiceTestDouble1()]);
		$this->assertFalse($service->hasProfile('other'));
		$this->assertFalse($service->hasProfile(' !!! '));
		$this->assertTrue($service->hasProfile(' PROFILE! '));
		try {
			$service->build('other', new AgentExecutionRequest([]));
			$this->fail('Unknown context profile must not be materialized.');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('Unknown agent context profile: other', $e->getMessage());
		}
	}

	public function testNoContextProvidersProduceNoProfiles(): void {
		$service = $this->service([]);
		$this->assertSame([], $service->getOptions());
		$this->assertFalse($service->hasProfile('profile'));
	}

	public function testNonProvidersAreIgnored(): void {
		$service = $this->service([new \stdClass(), new AgentContextProfileServiceTestDouble1()]);
		$this->assertCount(1, $service->getOptions());
	}

	public function testTwoProvidersCannotClaimSameProviderId(): void {
		$provider = new AgentContextProfileServiceTestDouble1();
		$service = $this->service([$provider, $provider]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent context profile provider: test');
		$service->getOptions();
	}

	public function testDifferentProvidersCannotClaimSameProfileId(): void {
		$service = $this->service([new AgentContextProfileServiceTestDouble1(), new OtherContextProfileFixture()]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Duplicate agent context profile id: profile');
		$service->getOptions();
	}

	public function testCachedProfilesRemainAvailableBetweenReads(): void {
		$lookups = 0;
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturnCallback(
			static function(string $interface) use (&$lookups): array {
				$lookups++;
				return [new AgentContextProfileServiceTestDouble1()];
			}
		);
		$service = new AgentContextProfileService($classMap);
		$this->assertTrue($service->hasProfile('profile'));
		$this->assertCount(1, $service->getOptions());
		$this->assertSame('profile', $service->build('profile', new AgentExecutionRequest([]))->getProfileId());
		$this->assertSame(1, $lookups);
	}

	private function service(array $providers): AgentContextProfileService {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->with(IAgentContextProfileProvider::class)->willReturn($providers);
		return new AgentContextProfileService($classMap);
	}
}

require_once __DIR__ . '/AgentContextProfileServiceTestDouble1.php';
