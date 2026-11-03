<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentToolProfileProvider;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantRuntime\Service\AgentToolProfileService;
use Base3\Api\IClassMap;
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
