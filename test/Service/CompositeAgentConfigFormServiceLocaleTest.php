<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantRuntime\Service\CompositeAgentConfigFormService;
use Base3\Api\IRequest;
use Base3\Language\Api\ILanguage;
use PHPUnit\Framework\TestCase;

final class CompositeAgentConfigFormServiceLocaleTest extends TestCase {

	public function testGermanLocaleUsesPluginOwnedTranslation(): void {
		$view = new RuntimeFormFakeView();
		$this->service('de_DE')->assignViewData($view, []);
		$form = $view->getAssigned('agent_config_form');
		$this->assertSame('Agenten-Runtime', $form['translations']['agent_runtime_label']);
		$this->assertSame('Bitte eine Agenten-Runtime auswählen.', $form['translations']['select_runtime_error']);
	}

	public function testUnsupportedLanguageUsesEnglishTranslation(): void {
		$view = new RuntimeFormFakeView();
		$this->service('xx_XX')->assignViewData($view, []);
		$form = $view->getAssigned('agent_config_form');
		$this->assertSame('Agent runtime', $form['translations']['agent_runtime_label']);
	}

	public function testLanguagePrefixNormalizesCaseAndTerritory(): void {
		$view = new RuntimeFormFakeView();
		$this->service('DE-de')->assignViewData($view, []);
		$form = $view->getAssigned('agent_config_form');
		$this->assertSame('Agenten-Runtime', $form['translations']['agent_runtime_label']);
	}

	public function testAssignedOptionsControlSelectorAndInactiveState(): void {
		$view = new RuntimeFormFakeView();
		$this->service('en')->assignViewData($view, [], [
			'form_id' => ' custom-id ',
			'show_runtime_selector' => 2,
			'runtime_active' => 'off'
		]);
		$form = $view->getAssigned('agent_config_form');
		$this->assertSame('custom-id', $form['form_id']);
		$this->assertFalse($form['show_runtime_selector']);
		$this->assertFalse($form['runtime_active']);
		$this->assertSame('custom-id_alpha', $form['runtime_sections'][0]['form']['form_id']);
	}

	public function testDefaultBooleanOptionsShowAndActivateRuntime(): void {
		$view = new RuntimeFormFakeView();
		$this->service('en')->assignViewData($view, [], []);
		$form = $view->getAssigned('agent_config_form');
		$this->assertTrue($form['show_runtime_selector']);
		$this->assertTrue($form['runtime_active']);
	}

	public function testSelectedRuntimeReceivesSelectedSettingsWhileOthersGetDefaults(): void {
		$alpha = $this->form('default-alpha');
		$beta = $this->form('default-beta');
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturnCallback(static fn(string $id): bool => in_array($id, ['alpha', 'beta'], true));
		$registry->method('getRuntimeOptions')->willReturn([
			['id' => 'alpha', 'label' => 'Alpha', 'description' => 'first'],
			['id' => 'beta', 'label' => 'Beta', 'description' => 'second']
		]);
		$registry->method('getConfigFormService')->willReturnCallback(
			static fn(string $id): IAgentRuntimeConfigFormService => $id === 'beta' ? $beta : $alpha
		);
		$view = new RuntimeFormFakeView();
		$this->service('en', $registry)->assignViewData($view, ['model' => 'chosen-beta'], [
			'selected_runtime' => ' BETA! ',
			'form_id' => 'agent_form'
		]);
		$form = $view->getAssigned('agent_config_form');
		$this->assertSame('beta', $form['selected_runtime']);
		$this->assertSame(['model' => 'default-alpha'], $form['runtime_sections'][0]['form']['values']);
		$this->assertSame(['model' => 'chosen-beta'], $form['runtime_sections'][1]['form']['values']);
		$this->assertSame('agent_form_alpha', $form['runtime_sections'][0]['form']['form_id']);
		$this->assertSame('agent_form_beta', $form['runtime_sections'][1]['form']['form_id']);
	}

	private function service(string $locale, ?IAgentRuntimeRegistry $registry = null): CompositeAgentConfigFormService {
		if ($registry === null) {
			$registry = $this->createStub(IAgentRuntimeRegistry::class);
			$registry->method('hasRuntime')->willReturnCallback(static fn(string $id): bool => $id === 'alpha');
			$registry->method('getRuntimeOptions')->willReturn([
				['id' => 'alpha', 'label' => 'Alpha', 'description' => 'Default']
			]);
			$registry->method('getConfigFormService')->willReturn($this->form('default-alpha'));
		}
		$request = $this->createStub(IRequest::class);
		$selector = $this->createStub(IAgentRuntimeSelector::class);
		$selector->method('getDefaultRuntimeId')->willReturn('alpha');
		$selector->method('selectRuntimeId')->willReturn('alpha');
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn($locale);
		return new CompositeAgentConfigFormService($request, $registry, $selector, $language);
	}

	private function form(string $default): IAgentRuntimeConfigFormService {
		$form = $this->createStub(IAgentRuntimeConfigFormService::class);
		$form->method('getDefaultSettings')->willReturn(['model' => $default]);
		$form->method('settingsToViewValues')->willReturnArgument(0);
		$form->method('getTemplate')->willReturn('Example/form.php');
		$form->method('getTemplateData')->willReturnCallback(static fn(array $values, array $options): array => [
			'values' => $values,
			'form_id' => $options['form_id']
		]);
		return $form;
	}
}

require_once __DIR__ . '/RuntimeFormFakeView.php';
