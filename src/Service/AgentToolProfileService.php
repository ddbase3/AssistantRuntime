<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentToolProfileProvider;
use AssistantFoundation\Api\IAgentToolProfileService;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\Api\IClassMap;

/**
 * Discovers tool-profile providers and resolves selected profiles into one
 * run-local tool set.
 */
final class AgentToolProfileService implements IAgentToolProfileService {

	/** @var array<string,IAgentToolProfileProvider>|null */
	private ?array $providers = null;

	/**
	 * @var array<string,array{provider:IAgentToolProfileProvider,option:array<string,mixed>}>|null
	 */
	private ?array $profiles = null;

	public function __construct(private readonly IClassMap $classMap) {}

	public static function getName(): string {
		return 'agenttoolprofileservice';
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

	public function resolve(array $profileIds, AgentExecutionRequest $request): IAgentToolSet {
		$grouped = [];
		foreach ($this->normalizeIds($profileIds) as $profileId) {
			$record = $this->getProfiles()[$profileId] ?? null;
			if (!is_array($record)) {
				throw new \RuntimeException('Unknown agent tool profile: ' . $profileId);
			}
			$providerId = $record['provider']::getProviderId();
			$grouped[$providerId]['provider'] = $record['provider'];
			$grouped[$providerId]['profiles'][] = $profileId;
		}

		$sets = [];
		foreach ($grouped as $entry) {
			$provider = $entry['provider'] ?? null;
			if (!$provider instanceof IAgentToolProfileProvider) {
				continue;
			}
			$sets[] = $provider->resolve(
				is_array($entry['profiles'] ?? null) ? $entry['profiles'] : [],
				$request
			);
		}

		return new CompositeAgentToolSet($sets);
	}

	/**
	 * @return array<string,array{provider:IAgentToolProfileProvider,option:array<string,mixed>}>
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
					throw new \RuntimeException('Duplicate agent tool profile id: ' . $profileId);
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

	/** @return array<string,IAgentToolProfileProvider> */
	private function getProviders(): array {
		if ($this->providers !== null) {
			return $this->providers;
		}

		$this->providers = [];
		foreach ($this->classMap->getInstancesByInterface(IAgentToolProfileProvider::class) as $provider) {
			if (!$provider instanceof IAgentToolProfileProvider) {
				continue;
			}
			$providerId = $this->normalizeId($provider::getProviderId());
			if ($providerId === '') {
				throw new \RuntimeException('Agent tool profile provider returned an empty provider id.');
			}
			if (isset($this->providers[$providerId])) {
				throw new \RuntimeException('Duplicate agent tool profile provider: ' . $providerId);
			}
			$this->providers[$providerId] = $provider;
		}
		return $this->providers;
	}

	/** @param array<int,mixed> $values @return array<int,string> */
	private function normalizeIds(array $values): array {
		$result = [];
		foreach ($values as $value) {
			$value = $this->normalizeId(is_scalar($value) || $value === null ? (string)$value : '');
			if ($value !== '') {
				$result[$value] = $value;
			}
		}
		return array_values($result);
	}

	private function normalizeId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
