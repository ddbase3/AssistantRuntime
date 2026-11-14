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

namespace Base3\AssistantRuntime\Test\Service\Fixtures;

use Base3\AssistantFoundation\Api\IAgentContextProfileProvider;
use Base3\AssistantFoundation\Dto\AgentContextProfileResult;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;

final class OtherContextProfileFixture implements IAgentContextProfileProvider {

	public static function getName(): string { return 'othercontextprofilefixture'; }
	public static function getProviderId(): string { return 'other'; }
	public function getOptions(): array { return [['id' => 'profile', 'label' => 'Duplicate']]; }
	public function hasProfile(string $profileId): bool { return $profileId === 'profile'; }
	public function build(string $profileId, AgentExecutionRequest $request): AgentContextProfileResult { return AgentContextProfileResult::empty($profileId); }
}
