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

/**
 * Shared facade that selects one runtime and delegates its configuration.
 */
final class CompositeAgentConfigFormService implements IAgentConfigFormService {

	public function __construct(
		private readonly IRequest $request,
		private readonly IAgentRuntimeRegistry $runtimeRegistry,
		private readonly IAgentRuntimeSelector $runtimeSelector
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
			'runtime_sections' => $sections
		]);
	}

	/** @param array<int,string> $errors */
	private function resolvePostedRuntimeId(?string $runtimeId, array &$errors): string {
		$runtimeId = $this->normalizeRuntimeId($runtimeId ?? (string)$this->request->request('agent_runtime', ''));
		if ($runtimeId === '') {
			$errors[] = 'Please select an agent runtime.';
			return '';
		}
		if (!$this->runtimeRegistry->hasRuntime($runtimeId)) {
			$errors[] = 'Selected agent runtime does not exist: ' . $runtimeId;
			return '';
		}

		return $runtimeId;
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
