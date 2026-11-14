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
