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
		$provider = new AgentToolProfileServiceTestDouble1($firstSet, $secondSet);
		$classMap = $this->createStub(IClassMap::class);
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

		return new AgentToolProfileServiceTestDouble2($capability);
	}
}

require_once __DIR__ . '/AgentToolProfileServiceTestDouble1.php';

require_once __DIR__ . '/AgentToolProfileServiceTestDouble2.php';
