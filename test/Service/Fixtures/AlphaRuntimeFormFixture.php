<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

use AssistantFoundation\Api\IAgentRuntimeConfigFormService;

class AlphaRuntimeFormFixture implements IAgentRuntimeConfigFormService {

	public static function getName(): string { return 'alpharuntimeformfixture'; }
	public static function getRuntimeId(): string { return ' ALPHA '; }
	public function getDefaultSettings(): array { return ['model' => 'default']; }
	public function normalizeSettings(array $settings): array { return $settings; }
	public function getPostedSettings(array &$errors): array { return []; }
	public function getPostedViewValues(): array { return []; }
	public function settingsToViewValues(array $settings): array { return $settings; }
	public function getConfigurationSummary(array $settings): array { return ['provider' => 'test', 'model' => 'default']; }
	public function getTemplate(): string { return ''; }
	public function getTemplateData(array $values, array $options = []): array { return $values; }
}
