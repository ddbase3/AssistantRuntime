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

use Base3\AssistantFoundation\Api\IAgentToolProfileProvider;
use Base3\AssistantFoundation\Api\IAgentToolSet;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantRuntime\Service\CompositeAgentToolSet;

final class OtherToolProfileFixture implements IAgentToolProfileProvider {

	public static function getName(): string { return 'othertoolprofilefixture'; }
	public static function getProviderId(): string { return 'other'; }
	public function getOptions(): array { return [['id' => 'first', 'label' => 'Duplicate']]; }
	public function hasProfile(string $profileId): bool { return $profileId === 'first'; }
	public function resolve(array $profileIds, AgentExecutionRequest $request): IAgentToolSet { return new CompositeAgentToolSet(); }
}
