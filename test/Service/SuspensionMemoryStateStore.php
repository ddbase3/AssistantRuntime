<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionResolution;
use AssistantFoundation\Dto\AgentSuspensionState;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\State\Api\IStateStore;
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
