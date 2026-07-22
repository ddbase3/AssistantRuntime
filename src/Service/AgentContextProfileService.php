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

use AssistantFoundation\Api\IAgentContextProfileProvider;
use AssistantFoundation\Api\IAgentContextProfileService;
use AssistantFoundation\Dto\AgentContextProfileResult;
use AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\Api\IClassMap;
use RuntimeException;

/**
 * Discovers context-profile providers and exposes one profile namespace.
 */
final class AgentContextProfileService implements IAgentContextProfileService {

	/** @var array<string,IAgentContextProfileProvider>|null */
	private ?array $providers = null;

	/**
	 * @var array<string,array{provider:IAgentContextProfileProvider,option:array<string,mixed>}>|null
	 */
	private ?array $profiles = null;

	public function __construct(private readonly IClassMap $classMap) {}

	public static function getName(): string {
		return 'agentcontextprofileservice';
	}

	public function getOptions(): array {
		$options = array_map(
			static fn(array $record): array => $record['option'],
			array_values($this->getProfiles())
		);

		usort($options, static function(array $left, array $right): int {
			$result = strcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
			return $result !== 0
				? $result
				: strcmp((string)($left['id'] ?? ''), (string)($right['id'] ?? ''));
		});

		return $options;
	}

	public function hasProfile(string $profileId): bool {
		$profileId = $this->normalizeId($profileId);
		return $profileId !== '' && isset($this->getProfiles()[$profileId]);
	}

	public function build(
		string $profileId,
		AgentExecutionRequest $request
	): AgentContextProfileResult {
		$profileId = $this->normalizeId($profileId);
		if ($profileId === '') {
			return AgentContextProfileResult::empty();
		}

		$profiles = $this->getProfiles();
		if (!isset($profiles[$profileId])) {
			throw new RuntimeException('Unknown agent context profile: ' . $profileId);
		}

		return $profiles[$profileId]['provider']->build($profileId, $request);
	}

	/**
	 * @return array<string,array{provider:IAgentContextProfileProvider,option:array<string,mixed>}>
	 */
	private function getProfiles(): array {
		if ($this->profiles !== null) {
			return $this->profiles;
		}

		$this->profiles = [];
		foreach ($this->getProviders() as $provider) {
			foreach ($provider->getOptions() as $option) {
				if (!is_array($option)) {
					continue;
				}

				$profileId = $this->normalizeId((string)($option['id'] ?? ''));
				if ($profileId === '' || !$provider->hasProfile($profileId)) {
					continue;
				}

				if (isset($this->profiles[$profileId])) {
					throw new RuntimeException('Duplicate agent context profile id: ' . $profileId);
				}

				$option['id'] = $profileId;
				$option['provider'] = $provider::getProviderId();
				$this->profiles[$profileId] = [
					'provider' => $provider,
					'option' => $option
				];
			}
		}

		return $this->profiles;
	}

	/** @return array<string,IAgentContextProfileProvider> */
	private function getProviders(): array {
		if ($this->providers !== null) {
			return $this->providers;
		}

		$this->providers = [];
		foreach ($this->classMap->getInstancesByInterface(IAgentContextProfileProvider::class) as $provider) {
			if (!$provider instanceof IAgentContextProfileProvider) {
				continue;
			}

			$providerId = $this->normalizeId($provider::getProviderId());
			if ($providerId === '') {
				throw new RuntimeException('Agent context profile provider returned an empty provider id.');
			}
			if (isset($this->providers[$providerId])) {
				throw new RuntimeException('Duplicate agent context profile provider: ' . $providerId);
			}

			$this->providers[$providerId] = $provider;
		}

		return $this->providers;
	}

	private function normalizeId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
