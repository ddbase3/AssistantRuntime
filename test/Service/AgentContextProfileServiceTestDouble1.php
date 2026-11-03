<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentContextProfileProvider;
use AssistantFoundation\Dto\AgentContextProfileResult;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentInstructionBlock;
use AssistantRuntime\Service\AgentContextProfileService;
use Base3\Api\IClassMap;
use PHPUnit\Framework\TestCase;

final class AgentContextProfileServiceTestDouble1 implements IAgentContextProfileProvider  {
			public static function getName(): string { return 'testcontextprofileprovider'; }
			public static function getProviderId(): string { return 'test'; }
			public function getOptions(): array { return [['id' => 'profile', 'label' => 'Profile']]; }
			public function hasProfile(string $profileId): bool { return $profileId === 'profile'; }
			public function build(string $profileId, AgentExecutionRequest $request): AgentContextProfileResult {
				return new AgentContextProfileResult($profileId, [
					new AgentInstructionBlock('block', 'Context')
				]);
			}
		}
