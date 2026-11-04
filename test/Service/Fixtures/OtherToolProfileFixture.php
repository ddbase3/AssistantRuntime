<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

use AssistantFoundation\Api\IAgentToolProfileProvider;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantRuntime\Service\CompositeAgentToolSet;

final class OtherToolProfileFixture implements IAgentToolProfileProvider {

	public static function getName(): string { return 'othertoolprofilefixture'; }
	public static function getProviderId(): string { return 'other'; }
	public function getOptions(): array { return [['id' => 'first', 'label' => 'Duplicate']]; }
	public function hasProfile(string $profileId): bool { return $profileId === 'first'; }
	public function resolve(array $profileIds, AgentExecutionRequest $request): IAgentToolSet { return new CompositeAgentToolSet(); }
}
