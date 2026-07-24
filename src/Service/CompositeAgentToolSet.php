<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentConfirmableToolSet;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentToolResult;

/**
 * Combines provider-owned run-local tool sets without copying their execution
 * logic or concrete tool implementations.
 */
final class CompositeAgentToolSet implements IAgentConfirmableToolSet {

	private AgentCapabilityCatalog $catalog;

	/** @var array<string,IAgentToolSet> */
	private array $setsByTool = [];

	/** @var array<int,string> */
	private array $warnings = [];

	/** @param array<int,IAgentToolSet> $sets */
	public function __construct(array $sets = []) {
		$capabilities = [];

		foreach ($sets as $set) {
			if (!$set instanceof IAgentToolSet) {
				throw new \InvalidArgumentException('Composite agent tool sets may contain only IAgentToolSet instances.');
			}

			$this->warnings = array_merge($this->warnings, $set->getWarnings());
			foreach ($set->getCatalog()->all() as $capability) {
				if (!$capability instanceof AgentCapability) {
					continue;
				}
				$name = $capability->getName();
				if (isset($this->setsByTool[$name])) {
					throw new \RuntimeException('Duplicate agent tool name across profile providers: ' . $name);
				}
				$this->setsByTool[$name] = $set;
				$capabilities[] = $capability;
			}
		}

		$this->warnings = array_values(array_unique(array_filter(array_map('trim', $this->warnings))));
		$this->catalog = new AgentCapabilityCatalog($capabilities);
	}

	public function getCatalog(): AgentCapabilityCatalog {
		return $this->catalog;
	}

	public function getWarnings(): array {
		return $this->warnings;
	}

	public function execute(
		string $callId,
		string $toolName,
		array $arguments,
		array $metadata = []
	): AgentToolResult {
		$set = $this->resolveSet($toolName);
		if ($set === null) {
			return AgentToolResult::failure(
				$callId,
				$toolName,
				$arguments,
				'unknown_tool',
				'The requested tool is not part of the resolved tool profiles.'
			);
		}

		return $set->execute($callId, $toolName, $arguments, $metadata);
	}

	public function prepareSuspension(
		string $callId,
		string $toolName,
		array $arguments,
		array $metadata = []
	): ?AgentSuspension {
		$set = $this->resolveSet($toolName);
		if ($set === null) {
			throw new \RuntimeException('The requested tool is not part of the resolved tool profiles: ' . $toolName);
		}

		return $set instanceof IAgentConfirmableToolSet
			? $set->prepareSuspension($callId, $toolName, $arguments, $metadata)
			: null;
	}

	public function resumeSuspension(
		AgentSuspension $suspension,
		AgentInteractionResponse $response,
		array $metadata = []
	): AgentToolResult {
		$requests = $suspension->getRequests();
		if (count($requests) !== 1) {
			throw new \RuntimeException('Tool suspension must contain exactly one interaction request.');
		}

		$toolName = $requests[0]->getAction()->getName();
		$set = $this->resolveSet($toolName);
		if (!$set instanceof IAgentConfirmableToolSet) {
			throw new \RuntimeException('The suspended tool is no longer available through a confirmable tool set: ' . $toolName);
		}

		return $set->resumeSuspension($suspension, $response, $metadata);
	}

	private function resolveSet(string $toolName): ?IAgentToolSet {
		$toolName = trim($toolName);
		return $toolName !== '' ? ($this->setsByTool[$toolName] ?? null) : null;
	}
}
