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

final class AgentToolProfileServiceTestDouble1 implements IAgentToolProfileProvider {
			public function __construct(
				private readonly IAgentToolSet $first,
				private readonly IAgentToolSet $second
			) {}
			public static function getName(): string { return 'testtoolprofileprovider'; }
			public static function getProviderId(): string { return 'test'; }
			public function getOptions(): array {
				return [
					['id' => 'first', 'label' => 'First'],
					['id' => 'second', 'label' => 'Second']
				];
			}
			public function hasProfile(string $profileId): bool {
				return in_array($profileId, ['first', 'second'], true);
			}
			public function resolve(array $profileIds, AgentExecutionRequest $request): IAgentToolSet {
				$sets = [];
				if (in_array('first', $profileIds, true)) $sets[] = $this->first;
				if (in_array('second', $profileIds, true)) $sets[] = $this->second;
				return new \Base3\AssistantRuntime\Service\CompositeAgentToolSet($sets);
			}
		}
