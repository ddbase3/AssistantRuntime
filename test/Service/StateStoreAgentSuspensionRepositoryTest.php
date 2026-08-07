<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\State\Api\IStateStore;
use PHPUnit\Framework\TestCase;

final class StateStoreAgentSuspensionRepositoryTest extends TestCase {

	public function testPendingSuspensionUsesTheSameCanonicalStateAsResume(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$suspension = $this->suspension('susp-1', 'conversation:scope-1');

		$handle = $repository->create($suspension, 900);
		$pending = $repository->findPending('conversation:scope-1');

		$this->assertNotNull($pending);
		$this->assertTrue($pending->isSuspended());
		$this->assertSame($suspension->getStatus(), $pending->getStatus());
		$this->assertSame($handle, $pending->getResumeHandle());
		$this->assertSame(
			array_map(static fn($request): array => $request->toArray(), $suspension->getRequests()),
			array_map(static fn($request): array => $request->toArray(), $pending->getInteractionRequests())
		);

		$claim = $repository->claim($handle);
		$repository->consume($claim);

		$this->assertNull($repository->findPending('conversation:scope-1'));
	}

	public function testSecondPendingSuspensionForTheSameScopeIsRejected(): void {
		$repository = new StateStoreAgentSuspensionRepository(new SuspensionMemoryStateStore());
		$handle = $repository->create($this->suspension('susp-old', 'conversation:scope-1'), 900);

		try {
			$repository->create($this->suspension('susp-new', 'conversation:scope-1'), 900);
			$this->fail('A conversation scope must not have two pending suspension states.');
		} catch (AgentSuspensionRepositoryException $exception) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_INVALID_STATE, $exception->getReason());
		}

		$this->assertSame($handle, $repository->findPending('conversation:scope-1')?->getResumeHandle());
	}

	private function suspension(string $id, string $scopeId): AgentSuspension {
		$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'write_value', ['value' => 'x']);
		$request = new AgentInteractionRequest(
			'air-1',
			AgentInteractionRequest::KIND_APPROVAL,
			$action,
			str_repeat('a', 64),
			'Confirm action',
			'Confirm the mutation.'
		);

		return new AgentSuspension(
			$id,
			AgentExecutionStatus::AWAITING_APPROVAL,
			[$request],
			[],
			'2026-08-07T12:00:00+00:00',
			[],
			$scopeId
		);
	}
}

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
