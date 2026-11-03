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
				return new \AssistantRuntime\Service\CompositeAgentToolSet($sets);
			}
		}
