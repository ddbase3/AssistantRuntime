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

final class AgentToolProfileServiceTestDouble2 implements IAgentToolSet {
			private AgentCapabilityCatalog $catalog;
			public function __construct(AgentCapability $capability) {
				$this->catalog = new AgentCapabilityCatalog([$capability]);
			}
			public function getCatalog(): AgentCapabilityCatalog { return $this->catalog; }
			public function getWarnings(): array { return []; }
			public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): AgentToolResult {
				return AgentToolResult::success($callId, $toolName, $arguments, $arguments);
			}
		}
