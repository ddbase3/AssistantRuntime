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

namespace Base3\AssistantRuntime\Test\Service\Fixtures;

use Base3\AssistantFoundation\Api\IAgentRuntimeConfigFormService;

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
