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

use Base3\AssistantFoundation\Api\IAgentContextProfileProvider;
use Base3\AssistantFoundation\Dto\AgentContextProfileResult;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentInstructionBlock;
use Base3\AssistantRuntime\Service\AgentContextProfileService;
use Base3\CoreFoundation\Api\IClassMap;
use PHPUnit\Framework\TestCase;

final class AgentContextProfileServiceTest extends TestCase {

	public function testDiscoversAndBuildsOneProfile(): void {
		$provider = new AgentContextProfileServiceTestDouble1();
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')
			->with(IAgentContextProfileProvider::class)
			->willReturn([$provider]);

		$service = new AgentContextProfileService($classMap);

		self::assertTrue($service->hasProfile('profile'));
		self::assertSame('test', $service->getOptions()[0]['provider']);
		self::assertSame(
			'Context',
			$service->build('profile', new AgentExecutionRequest([]))->getBlocks()[0]->getContent()
		);
	}

	public function testEmptyProfileBuildsEmptyResult(): void {
		$classMap = $this->createMock(IClassMap::class);
		$classMap->expects(self::never())->method('getInstancesByInterface');

		$result = (new AgentContextProfileService($classMap))->build('', new AgentExecutionRequest([]));

		self::assertSame([], $result->getBlocks());
	}
}

require_once __DIR__ . '/AgentContextProfileServiceTestDouble1.php';
