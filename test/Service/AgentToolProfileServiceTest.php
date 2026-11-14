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

use Base3\AssistantFoundation\Api\IAgentToolProfileProvider;
use Base3\AssistantFoundation\Api\IAgentToolSet;
use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\AssistantRuntime\Service\AgentToolProfileService;
use Base3\CoreFoundation\Api\IClassMap;
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
		new \Base3\AssistantRuntime\Service\CompositeAgentToolSet([$set, $set]);
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
