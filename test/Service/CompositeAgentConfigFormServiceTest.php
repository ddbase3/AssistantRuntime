<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantRuntime\Service\CompositeAgentConfigFormService;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Language\Api\ILanguage;
use PHPUnit\Framework\TestCase;

final class CompositeAgentConfigFormServiceTest extends TestCase {

	public function testPostedSettingsValidateOnlyExplicitRuntime(): void {
		$request = $this->createStub(IRequest::class);
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$selector = $this->createStub(IAgentRuntimeSelector::class);
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('en');
		$missionBay = $this->createMock(IAgentRuntimeConfigFormService::class);
		$neuron = $this->createMock(IAgentRuntimeConfigFormService::class);

		$registry->method('hasRuntime')->willReturnCallback(
			static fn(string $runtimeId): bool => in_array($runtimeId, ['missionbay', 'neuronai'], true)
		);
		$registry->method('getConfigFormService')->willReturnCallback(
			static fn(string $runtimeId): IAgentRuntimeConfigFormService => $runtimeId === 'neuronai' ? $neuron : $missionBay
		);
		$missionBay->expects($this->once())
			->method('getPostedSettings')
			->willReturn(['llm' => 'default']);
		$neuron->expects($this->never())->method('getPostedSettings');

		$service = new CompositeAgentConfigFormService($request, $registry, $selector, $language);
		$errors = [];
		$settings = $service->getPostedSettings($errors, 'missionbay');

		$this->assertSame([], $errors);
		$this->assertSame('missionbay', $settings['agent_runtime'] ?? null);
		$this->assertSame('default', $settings['llm'] ?? null);
	}

	public function testAssignViewDataBuildsOneSectionPerRuntime(): void {
		$request = $this->createStub(IRequest::class);
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$selector = $this->createStub(IAgentRuntimeSelector::class);
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('en');
		$view = new RuntimeFormFakeView();
		$missionBay = $this->createStub(IAgentRuntimeConfigFormService::class);
		$neuron = $this->createStub(IAgentRuntimeConfigFormService::class);

		$registry->method('hasRuntime')->willReturn(true);
		$registry->method('getRuntimeOptions')->willReturn([
			['id' => 'missionbay', 'label' => 'MissionBay', 'description' => 'Flow runtime'],
			['id' => 'neuronai', 'label' => 'Neuron AI', 'description' => 'Neuron runtime']
		]);
		$registry->method('getConfigFormService')->willReturnCallback(
			static fn(string $runtimeId): IAgentRuntimeConfigFormService => $runtimeId === 'neuronai' ? $neuron : $missionBay
		);
		foreach ([$missionBay, $neuron] as $form) {
			$form->method('getDefaultSettings')->willReturn([]);
			$form->method('settingsToViewValues')->willReturnArgument(0);
			$form->method('getTemplate')->willReturn('/tmp/runtime.php');
			$form->method('getTemplateData')->willReturnCallback(
				static fn(array $values, array $options): array => ['values' => $values, 'form_id' => $options['form_id'] ?? '']
			);
		}

		$service = new CompositeAgentConfigFormService($request, $registry, $selector, $language);
		$service->assignViewData($view, [
			'llm' => 'default',
			'agent_flow' => ['nodes' => [['id' => 'assistant']]]
		], [
			'form_id' => 'agent_editor',
			'selected_runtime' => 'missionbay',
			'show_runtime_selector' => false,
			'runtime_active' => true
		]);

		$form = $view->getAssigned('agent_config_form');
		$this->assertSame('agent_editor', $form['form_id'] ?? null);
		$this->assertSame('missionbay', $form['selected_runtime'] ?? null);
		$this->assertFalse($form['show_runtime_selector'] ?? true);
		$this->assertCount(2, $form['runtime_sections'] ?? []);
		$this->assertSame('agent_editor_missionbay', $form['runtime_sections'][0]['form']['form_id'] ?? null);
		$this->assertSame(
			['nodes' => [['id' => 'assistant']]],
			$form['runtime_sections'][0]['form']['values']['agent_flow'] ?? null
		);
		$this->assertSame('agent_editor_neuronai', $form['runtime_sections'][1]['form']['form_id'] ?? null);
	}
}

final class RuntimeFormFakeView implements IMvcView {

	private array $assigned = [];

	public function setPath(string $path = '.'): void {}

	public function assign(string $key, $value): void {
		$this->assigned[$key] = $value;
	}

	public function setTemplate(string $template = 'default'): void {}

	public function loadTemplate(): string {
		return '';
	}

	public function loadBricks(string $set, string $language = ''): void {}

	public function getBricks(string $set): ?array {
		return null;
	}

	public function getAssigned(string $key): mixed {
		return $this->assigned[$key] ?? null;
	}
}
