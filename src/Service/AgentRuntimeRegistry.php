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

use AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeService;
use Base3\Api\IClassMap;
use RuntimeException;

final class AgentRuntimeRegistry implements IAgentRuntimeRegistry {

	/** @var array<string,IAgentRuntimeService>|null */
	private ?array $executionServices = null;

	/** @var array<string,IAgentRuntimeConfigFormService>|null */
	private ?array $configFormServices = null;

	public function __construct(private readonly IClassMap $classMap) {}

	public static function getName(): string {
		return 'agentruntimeregistry';
	}

	public function getRuntimeIds(): array {
		return array_keys($this->getPairedRuntimes());
	}

	public function getRuntimeOptions(): array {
		$options = [];

		foreach ($this->getPairedRuntimes() as $runtimeId => $runtime) {
			$service = $runtime['execution'];
			$options[] = [
				'id' => $runtimeId,
				'label' => trim($service::getRuntimeLabel()) ?: $runtimeId,
				'description' => trim($service::getRuntimeDescription()),
				'default_priority' => $service::getDefaultPriority()
			];
		}

		usort($options, static function(array $left, array $right): int {
			$labelCompare = strcasecmp((string)$left['label'], (string)$right['label']);
			return $labelCompare !== 0
				? $labelCompare
				: strcmp((string)$left['id'], (string)$right['id']);
		});

		return $options;
	}

	public function hasRuntime(string $runtimeId): bool {
		$runtimeId = $this->normalizeRuntimeId($runtimeId);
		return $runtimeId !== '' && isset($this->getPairedRuntimes()[$runtimeId]);
	}

	public function getExecutionService(string $runtimeId): IAgentRuntimeService {
		$runtimeId = $this->requireRuntimeId($runtimeId);
		return $this->getPairedRuntimes()[$runtimeId]['execution'];
	}

	public function getConfigFormService(string $runtimeId): IAgentRuntimeConfigFormService {
		$runtimeId = $this->requireRuntimeId($runtimeId);
		return $this->getPairedRuntimes()[$runtimeId]['config'];
	}

	/**
	 * @return array<string,array{execution:IAgentRuntimeService,config:IAgentRuntimeConfigFormService}>
	 */
	private function getPairedRuntimes(): array {
		$executionServices = $this->loadExecutionServices();
		$configFormServices = $this->loadConfigFormServices();
		$runtimeIds = array_values(array_unique(array_merge(
			array_keys($executionServices),
			array_keys($configFormServices)
		)));
		sort($runtimeIds);
		$paired = [];

		foreach ($runtimeIds as $runtimeId) {
			if (!isset($executionServices[$runtimeId])) {
				throw new RuntimeException('Agent runtime has no execution service: ' . $runtimeId);
			}
			if (!isset($configFormServices[$runtimeId])) {
				throw new RuntimeException('Agent runtime has no configuration form service: ' . $runtimeId);
			}
			$paired[$runtimeId] = [
				'execution' => $executionServices[$runtimeId],
				'config' => $configFormServices[$runtimeId]
			];
		}

		return $paired;
	}

	/** @return array<string,IAgentRuntimeService> */
	private function loadExecutionServices(): array {
		if ($this->executionServices !== null) {
			return $this->executionServices;
		}

		$this->executionServices = [];
		foreach ($this->classMap->getInstancesByInterface(IAgentRuntimeService::class) as $service) {
			if (!$service instanceof IAgentRuntimeService) {
				continue;
			}
			$runtimeId = $this->normalizeRuntimeId($service::getRuntimeId());
			$this->assertUniqueRuntime($this->executionServices, $runtimeId, 'execution service');
			$this->executionServices[$runtimeId] = $service;
		}

		return $this->executionServices;
	}

	/** @return array<string,IAgentRuntimeConfigFormService> */
	private function loadConfigFormServices(): array {
		if ($this->configFormServices !== null) {
			return $this->configFormServices;
		}

		$this->configFormServices = [];
		foreach ($this->classMap->getInstancesByInterface(IAgentRuntimeConfigFormService::class) as $service) {
			if (!$service instanceof IAgentRuntimeConfigFormService) {
				continue;
			}
			$runtimeId = $this->normalizeRuntimeId($service::getRuntimeId());
			$this->assertUniqueRuntime($this->configFormServices, $runtimeId, 'configuration form service');
			$this->configFormServices[$runtimeId] = $service;
		}

		return $this->configFormServices;
	}

	/** @param array<string,mixed> $services */
	private function assertUniqueRuntime(array $services, string $runtimeId, string $serviceType): void {
		if ($runtimeId === '') {
			throw new RuntimeException('Agent runtime ' . $serviceType . ' returned an empty runtime id.');
		}
		if (isset($services[$runtimeId])) {
			throw new RuntimeException('Duplicate agent runtime ' . $serviceType . ': ' . $runtimeId);
		}
	}

	private function requireRuntimeId(string $runtimeId): string {
		$runtimeId = $this->normalizeRuntimeId($runtimeId);
		if ($runtimeId === '' || !isset($this->getPairedRuntimes()[$runtimeId])) {
			throw new RuntimeException('Unknown agent runtime: ' . ($runtimeId !== '' ? $runtimeId : '[empty]'));
		}
		return $runtimeId;
	}

	private function normalizeRuntimeId(string $runtimeId): string {
		$runtimeId = strtolower(trim($runtimeId));
		return preg_replace('/[^a-z0-9._-]+/', '', $runtimeId) ?? '';
	}
}
