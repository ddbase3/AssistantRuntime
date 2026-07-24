<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentToolProfileProvider;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantRuntime\Service\AgentToolProfileService;
use Base3\Api\IClassMap;
use PHPUnit\Framework\TestCase;

final class AgentToolProfileServiceTest extends TestCase {

	public function testDiscoversAndCombinesSelectedProfiles(): void {
		$firstSet = $this->createToolSet('first_tool');
		$secondSet = $this->createToolSet('second_tool');
		$provider = new class($firstSet, $secondSet) implements IAgentToolProfileProvider {
			public function __construct(
				private readonly IAgentToolSet $first,
				private readonly IAgentToolSet $second
			) {}
			public static function getName(): string { return 'testtoolprofileprovider'; }
			public static function getProviderId(): string { return 'test'; }
			public function getOptions(): array {
				return [
					['id' => 'first', 'label' => 'First'],
					['id' => 'second', 'label' => 'Second']
				];
			}
			public function hasProfile(string $profileId): bool {
				return in_array($profileId, ['first', 'second'], true);
			}
			public function resolve(array $profileIds, AgentExecutionRequest $request): IAgentToolSet {
				$sets = [];
				if (in_array('first', $profileIds, true)) $sets[] = $this->first;
				if (in_array('second', $profileIds, true)) $sets[] = $this->second;
				return new \AssistantRuntime\Service\CompositeAgentToolSet($sets);
			}
		};
		$classMap = $this->createMock(IClassMap::class);
		$classMap->method('getInstancesByInterface')
			->with(IAgentToolProfileProvider::class)
			->willReturn([$provider]);

		$service = new AgentToolProfileService($classMap);
		$set = $service->resolve(['first', 'second'], new AgentExecutionRequest([]));

		self::assertSame(['first_tool', 'second_tool'], $set->getCatalog()->names());
		self::assertTrue($service->hasProfile('first'));
		self::assertSame('test', $service->getOptions()[0]['provider']);
	}

	public function testRejectsDuplicateToolNamesAcrossProviders(): void {
		$set = $this->createToolSet('duplicate');
		$this->expectException(\RuntimeException::class);
		new \AssistantRuntime\Service\CompositeAgentToolSet([$set, $set]);
	}

	private function createToolSet(string $name): IAgentToolSet {
		$capability = new AgentCapability(
			$name,
			$name,
			$name,
			'test',
			[],
			0,
			[
				'type' => 'function',
				'function' => [
					'name' => $name,
					'description' => $name,
					'parameters' => ['type' => 'object', 'properties' => [], 'required' => []]
				]
			]
		);

		return new class($capability) implements IAgentToolSet {
			private AgentCapabilityCatalog $catalog;
			public function __construct(AgentCapability $capability) {
				$this->catalog = new AgentCapabilityCatalog([$capability]);
			}
			public function getCatalog(): AgentCapabilityCatalog { return $this->catalog; }
			public function getWarnings(): array { return []; }
			public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): AgentToolResult {
				return AgentToolResult::success($callId, $toolName, $arguments, $arguments);
			}
		};
	}
}
