<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

use AssistantFoundation\Api\IAgentContextProfileProvider;
use AssistantFoundation\Dto\AgentContextProfileResult;
use AssistantFoundation\Dto\AgentExecutionRequest;

final class OtherContextProfileFixture implements IAgentContextProfileProvider {

	public static function getName(): string { return 'othercontextprofilefixture'; }
	public static function getProviderId(): string { return 'other'; }
	public function getOptions(): array { return [['id' => 'profile', 'label' => 'Duplicate']]; }
	public function hasProfile(string $profileId): bool { return $profileId === 'profile'; }
	public function build(string $profileId, AgentExecutionRequest $request): AgentContextProfileResult { return AgentContextProfileResult::empty($profileId); }
}
