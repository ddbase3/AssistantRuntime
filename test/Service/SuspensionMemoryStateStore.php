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

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentSuspensionResolution;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\CoreFoundation\State\Api\IStateStore;
use PHPUnit\Framework\TestCase;

final class SuspensionMemoryStateStore implements IStateStore {

	private array $values = [];

	public function get(string $key, mixed $default = null): mixed {
		return $this->values[$key] ?? $default;
	}

	public function has(string $key): bool {
		return array_key_exists($key, $this->values);
	}

	public function set(string $key, mixed $value, ?int $ttlSeconds = null): void {
		$this->values[$key] = $value;
	}

	public function delete(string $key): bool {
		$exists = array_key_exists($key, $this->values);
		unset($this->values[$key]);
		return $exists;
	}

	public function setIfNotExists(string $key, mixed $value, ?int $ttlSeconds = null): bool {
		if ($this->has($key)) {
			return false;
		}
		$this->values[$key] = $value;
		return true;
	}

	public function listKeys(string $prefix): array {
		return array_values(array_filter(
			array_keys($this->values),
			static fn(string $key): bool => str_starts_with($key, $prefix)
		));
	}

	public function flush(): void {
	}
}
