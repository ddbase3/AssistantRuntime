<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentContextProfileProvider;
use AssistantFoundation\Dto\AgentContextProfileResult;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentInstructionBlock;
use AssistantRuntime\Service\AgentContextProfileService;
use Base3\Api\IClassMap;
use PHPUnit\Framework\TestCase;

final class AgentContextProfileServiceTest extends TestCase {

	public function testDiscoversAndBuildsOneProfile(): void {
		$provider = new class implements IAgentContextProfileProvider {
			public static function getName(): string { return 'testcontextprofileprovider'; }
			public static function getProviderId(): string { return 'test'; }
			public function getOptions(): array { return [['id' => 'profile', 'label' => 'Profile']]; }
			public function hasProfile(string $profileId): bool { return $profileId === 'profile'; }
			public function build(string $profileId, AgentExecutionRequest $request): AgentContextProfileResult {
				return new AgentContextProfileResult($profileId, [
					new AgentInstructionBlock('block', 'Context')
				]);
			}
		};
		$classMap = $this->createMock(IClassMap::class);
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
