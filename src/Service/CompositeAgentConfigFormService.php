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
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentConfigFormService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Language\Api\ILanguage;

/**
 * Shared facade that selects one runtime and delegates its configuration.
 */
final class CompositeAgentConfigFormService implements IAgentConfigFormService {

	/** @var array<string,string>|null */
	private ?array $translations = null;

	public function __construct(
		private readonly IRequest $request,
		private readonly IAgentRuntimeRegistry $runtimeRegistry,
		private readonly IAgentRuntimeSelector $runtimeSelector,
		private readonly ILanguage $language
	) {}

	public static function getName(): string {
		return 'compositeagentconfigformservice';
	}

	public function getDefaultSettings(): array {
		$runtimeId = $this->runtimeSelector->getDefaultRuntimeId();

		return array_merge(
			['agent_runtime' => $runtimeId],
			$this->runtimeRegistry->getConfigFormService($runtimeId)->getDefaultSettings()
		);
	}

	public function normalizeSettings(array $settings): array {
		$runtimeId = $this->runtimeSelector->selectRuntimeId($settings);

		return array_merge(
			['agent_runtime' => $runtimeId],
			$this->runtimeRegistry->getConfigFormService($runtimeId)->normalizeSettings($settings)
		);
	}

	public function getPostedSettings(array &$errors, ?string $runtimeId = null): array {
		$runtimeId = $this->resolvePostedRuntimeId($runtimeId, $errors);
		if ($runtimeId === '') {
			return [];
		}

		return array_merge(
			['agent_runtime' => $runtimeId],
			$this->runtimeRegistry->getConfigFormService($runtimeId)->getPostedSettings($errors)
		);
	}

	public function getPostedViewValues(?string $runtimeId = null): array {
		$runtimeId = $this->normalizeRuntimeId($runtimeId ?? (string)$this->request->request('agent_runtime', ''));
		if (!$this->runtimeRegistry->hasRuntime($runtimeId)) {
			$runtimeId = $this->runtimeSelector->getDefaultRuntimeId();
		}

		$formService = $this->runtimeRegistry->getConfigFormService($runtimeId);
		$runtimeValues = $formService->getPostedViewValues();

		return array_merge(
			[
				'agent_runtime' => $runtimeId,
				'agent_runtime_summary' => $formService->getConfigurationSummary($runtimeValues)
			],
			$runtimeValues
		);
	}

	public function settingsToViewValues(array $settings): array {
		$runtimeId = $this->runtimeSelector->selectRuntimeId($settings);
		$formService = $this->runtimeRegistry->getConfigFormService($runtimeId);

		return array_merge(
			[
				'agent_runtime' => $runtimeId,
				'agent_runtime_summary' => $formService->getConfigurationSummary($settings)
			],
			$formService->settingsToViewValues($settings)
		);
	}

	public function assignViewData(IMvcView $view, array $settings, array $options = []): void {
		$runtimeId = $this->normalizeRuntimeId((string)($options['selected_runtime'] ?? ''));
		if (!$this->runtimeRegistry->hasRuntime($runtimeId)) {
			$runtimeId = $this->runtimeSelector->selectRuntimeId($settings);
		}

		$formId = trim((string)($options['form_id'] ?? 'base3_agent_config'));
		if ($formId === '') {
			$formId = 'base3_agent_config';
		}

		$showRuntimeSelector = !array_key_exists('show_runtime_selector', $options)
			|| $this->toBool($options['show_runtime_selector']);
		$runtimeActive = !array_key_exists('runtime_active', $options)
			|| $this->toBool($options['runtime_active']);
		$sections = [];

		foreach ($this->runtimeRegistry->getRuntimeOptions() as $runtimeOption) {
			$id = (string)$runtimeOption['id'];
			$formService = $this->runtimeRegistry->getConfigFormService($id);
			$runtimeValues = $id === $runtimeId
				? $formService->settingsToViewValues($settings)
				: $formService->settingsToViewValues($formService->getDefaultSettings());
			$sections[] = [
				'id' => $id,
				'label' => (string)$runtimeOption['label'],
				'description' => (string)$runtimeOption['description'],
				'template' => $formService->getTemplate(),
				'form' => $formService->getTemplateData($runtimeValues, array_merge($options, [
					'form_id' => $formId . '_' . $id
				]))
			];
		}

		$view->assign('agent_config_template', DIR_PLUGIN . 'AssistantRuntime/tpl/Content/AgentRuntimeConfigFormSection.php');
		$view->assign('agent_config_form', [
			'form_id' => $formId,
			'selected_runtime' => $runtimeId,
			'show_runtime_selector' => $showRuntimeSelector,
			'runtime_active' => $runtimeActive,
			'runtime_options' => $this->runtimeRegistry->getRuntimeOptions(),
			'runtime_sections' => $sections,
			'translations' => $this->getTranslations()
		]);
	}

	/** @param array<int,string> $errors */
	private function resolvePostedRuntimeId(?string $runtimeId, array &$errors): string {
		$runtimeId = $this->normalizeRuntimeId($runtimeId ?? (string)$this->request->request('agent_runtime', ''));
		if ($runtimeId === '') {
			$errors[] = $this->translate('select_runtime_error', 'Please select an agent runtime.');
			return '';
		}
		if (!$this->runtimeRegistry->hasRuntime($runtimeId)) {
			$errors[] = sprintf($this->translate('runtime_missing_error', 'Selected agent runtime does not exist: %s'), $runtimeId);
			return '';
		}

		return $runtimeId;
	}

	/** @return array<string,string> */
	private function getTranslations(): array {
		if ($this->translations !== null) {
			return $this->translations;
		}

		$language = strtolower(str_replace('_', '-', trim($this->language->getLanguage())));
		$language = explode('-', $language)[0] ?? 'en';
		if (!in_array($language, ['de', 'en', 'fr', 'es', 'ru'], true)) {
			$language = 'en';
		}

		$basePath = defined('DIR_PLUGIN') ? DIR_PLUGIN . 'AssistantRuntime/lang/AgentRuntimeConfigForm/' : '';
		$fallback = $basePath === '' ? [] : $this->readTranslationFile($basePath . 'en.ini');
		$current = $language === 'en' || $basePath === ''
			? []
			: $this->readTranslationFile($basePath . $language . '.ini');
		$this->translations = array_merge([
			'agent_runtime_label' => 'Agent runtime',
			'select_runtime_error' => 'Please select an agent runtime.',
			'runtime_missing_error' => 'Selected agent runtime does not exist: %s'
		], $fallback, $current);

		return $this->translations;
	}

	private function translate(string $key, string $fallback): string {
		$value = $this->getTranslations()[$key] ?? null;
		return is_scalar($value) && trim((string)$value) !== ''
			? trim((string)$value)
			: $fallback;
	}

	/** @return array<string,string> */
	private function readTranslationFile(string $filename): array {
		if (!is_file($filename) || !is_readable($filename)) {
			return [];
		}

		$data = parse_ini_file($filename, true);
		$section = is_array($data['agent_runtime_config'] ?? null) ? $data['agent_runtime_config'] : [];

		return array_filter($section, static fn($value): bool => is_scalar($value));
	}
	private function normalizeRuntimeId(string $runtimeId): string {
		$runtimeId = strtolower(trim($runtimeId));

		return preg_replace('/[^a-z0-9._-]+/', '', $runtimeId) ?? '';
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value === 1;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}
}
