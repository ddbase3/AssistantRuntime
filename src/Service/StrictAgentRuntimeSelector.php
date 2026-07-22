<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 *
 * AssistantRuntime provides shared runtime composition for assistants and
 * agent implementations. Contracts remain in AssistantFoundation while
 * concrete registries, routers, sinks and configuration forms live here.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/assistantruntime
 * https://github.com/ddbase3/AssistantRuntime
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use RuntimeException;

class StrictAgentRuntimeSelector implements IAgentRuntimeSelector {

	public function __construct(protected readonly IAgentRuntimeRegistry $runtimeRegistry) {}

	public static function getName(): string {
		return 'strictagentruntimeselector';
	}

	public function selectRuntimeId(array $agentConfiguration): string {
		$runtimeId = $this->normalizeRuntimeId((string)($agentConfiguration['agent_runtime'] ?? ''));
		if ($runtimeId !== '') {
			if (!$this->runtimeRegistry->hasRuntime($runtimeId)) {
				throw new RuntimeException('Configured agent runtime does not exist: ' . $runtimeId);
			}
			return $runtimeId;
		}

		return $this->getDefaultRuntimeId();
	}

	public function getDefaultRuntimeId(): string {
		$options = $this->runtimeRegistry->getRuntimeOptions();
		if ($options === []) {
			throw new RuntimeException('No agent runtime is installed.');
		}
		if (count($options) === 1) {
			return (string)$options[0]['id'];
		}

		$highestPriority = max(array_map(
			static fn(array $option): int => (int)($option['default_priority'] ?? 0),
			$options
		));
		$candidates = array_values(array_filter(
			$options,
			static fn(array $option): bool => (int)($option['default_priority'] ?? 0) === $highestPriority
		));
		if (count($candidates) === 1) {
			return (string)$candidates[0]['id'];
		}

		throw new RuntimeException('Multiple agent runtimes are installed. Store agent_runtime explicitly.');
	}

	protected function normalizeRuntimeId(string $runtimeId): string {
		$runtimeId = strtolower(trim($runtimeId));
		return preg_replace('/[^a-z0-9._-]+/', '', $runtimeId) ?? '';
	}
}
