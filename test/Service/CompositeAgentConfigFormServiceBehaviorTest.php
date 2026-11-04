<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantRuntime\Service\CompositeAgentConfigFormService;
use Base3\Api\IRequest;
use Base3\Language\Api\ILanguage;
use PHPUnit\Framework\TestCase;

final class CompositeAgentConfigFormServiceBehaviorTest extends TestCase {

	public function testDefaultSettingsAreObtainedOnlyFromTheDefaultRuntime(): void {
		$form = $this->createMock(IAgentRuntimeConfigFormService::class);
		$form->expects($this->once())->method('getDefaultSettings')->willReturn(['model' => 'one']);
		$service = $this->service($form);
		$this->assertSame(['agent_runtime' => 'alpha', 'model' => 'one'], $service->getDefaultSettings());
	}

	public function testNormalizeSettingsDelegatesToExplicitRuntime(): void {
		$form = $this->createMock(IAgentRuntimeConfigFormService::class);
		$form->expects($this->once())->method('normalizeSettings')->with(['agent_runtime' => 'beta', 'temperature' => '0.5'])
			->willReturn(['temperature' => 0.5]);
		$service = $this->service($form, 'beta');
		$this->assertSame(['agent_runtime' => 'beta', 'temperature' => 0.5],
			$service->normalizeSettings(['agent_runtime' => 'beta', 'temperature' => '0.5']));
	}

	public function testMissingPostedRuntimeDoesNotReadAnyRuntimeForm(): void {
		$form = $this->createMock(IAgentRuntimeConfigFormService::class);
		$form->expects($this->never())->method('getPostedSettings');
		$service = $this->service($form, 'alpha', '');
		$errors = [];
		$this->assertSame([], $service->getPostedSettings($errors));
		$this->assertCount(1, $errors);
		$this->assertNotEmpty($errors[0]);
	}

	public function testUnknownPostedRuntimeIsRejectedWithoutUsingAnotherRuntime(): void {
		$form = $this->createMock(IAgentRuntimeConfigFormService::class);
		$form->expects($this->never())->method('getPostedSettings');
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturn(false);
		$service = $this->service($form, 'alpha', 'unused', $registry);
		$errors = [];
		$this->assertSame([], $service->getPostedSettings($errors, 'MISSING!'));
		$this->assertCount(1, $errors);
		$this->assertStringContainsString('missing', strtolower($errors[0]));
	}

	public function testExplicitPostedRuntimeIsNormalizedAndFormErrorsArePreserved(): void {
		$form = $this->createMock(IAgentRuntimeConfigFormService::class);
		$form->expects($this->once())->method('getPostedSettings')->willReturnCallback(
			static function(array &$errors): array {
				$errors[] = 'Invalid temperature';
				return ['temperature' => 1];
			}
		);
		$service = $this->service($form);
		$errors = [];
		$this->assertSame(['agent_runtime' => 'alpha', 'temperature' => 1], $service->getPostedSettings($errors, ' ALPHA! '));
		$this->assertSame(['Invalid temperature'], $errors);
	}

	public function testInvalidPostedViewRuntimeUsesSelectedDefaultRuntime(): void {
		$form = $this->createMock(IAgentRuntimeConfigFormService::class);
		$form->expects($this->once())->method('getPostedViewValues')->willReturn(['model' => 'preview']);
		$form->expects($this->once())->method('getConfigurationSummary')->with(['model' => 'preview'])
			->willReturn(['provider' => 'example', 'model' => 'preview']);
		$registry = $this->createStub(IAgentRuntimeRegistry::class);
		$registry->method('hasRuntime')->willReturnCallback(static fn(string $id): bool => $id === 'alpha');
		$registry->method('getConfigFormService')->with('alpha')->willReturn($form);
		$service = $this->service($form, 'alpha', 'invalid', $registry);
		$this->assertSame([
			'agent_runtime' => 'alpha',
			'agent_runtime_summary' => ['provider' => 'example', 'model' => 'preview'],
			'model' => 'preview'
		], $service->getPostedViewValues());
	}

	public function testSettingsToViewValuesPassesOriginalSettingsForSummary(): void {
		$form = $this->createMock(IAgentRuntimeConfigFormService::class);
		$form->expects($this->once())->method('getConfigurationSummary')->with(['agent_runtime' => 'alpha', 'model' => 'loaded'])
			->willReturn(['provider' => 'p', 'model' => 'loaded']);
		$form->expects($this->once())->method('settingsToViewValues')->with(['agent_runtime' => 'alpha', 'model' => 'loaded'])
			->willReturn(['model' => 'loaded']);
		$this->assertSame([
			'agent_runtime' => 'alpha',
			'agent_runtime_summary' => ['provider' => 'p', 'model' => 'loaded'],
			'model' => 'loaded'
		], $this->service($form)->settingsToViewValues(['agent_runtime' => 'alpha', 'model' => 'loaded']));
	}

	public function testAssignViewDataAppliesDefaultsAndTranslatedLabels(): void {
		$form = $this->createStub(IAgentRuntimeConfigFormService::class);
		$form->method('getDefaultSettings')->willReturn(['model' => 'default']);
		$form->method('settingsToViewValues')->willReturnArgument(0);
		$form->method('getTemplate')->willReturn('runtime.php');
		$form->method('getTemplateData')->willReturnCallback(
			static fn(array $values, array $options): array => ['values' => $values, 'form_id' => $options['form_id']]
		);
		$view = new RuntimeFormFakeView();
		$service = $this->service($form);
		$service->assignViewData($view, ['model' => 'selected'], [
			'form_id' => ' ', 'selected_runtime' => ' UNKNOWN ',
			'show_runtime_selector' => 'off', 'runtime_active' => 'yes'
		]);
		$formData = $view->getAssigned('agent_config_form');
		$this->assertSame('base3_agent_config', $formData['form_id']);
		$this->assertSame('alpha', $formData['selected_runtime']);
		$this->assertFalse($formData['show_runtime_selector']);
		$this->assertTrue($formData['runtime_active']);
		$this->assertCount(1, $formData['runtime_sections']);
		$this->assertSame('base3_agent_config_alpha', $formData['runtime_sections'][0]['form']['form_id']);
		$this->assertSame(['model' => 'selected'], $formData['runtime_sections'][0]['form']['values']);
		$this->assertNotEmpty($formData['translations']['agent_runtime_label']);
	}

	private function service(
		IAgentRuntimeConfigFormService $form,
		string $runtimeId = 'alpha',
		string $postedRuntime = 'alpha',
		?IAgentRuntimeRegistry $registry = null
	): CompositeAgentConfigFormService {
		$request = $this->createStub(IRequest::class);
		$request->method('request')->willReturn($postedRuntime);
		$providedRegistry = $registry !== null;
		$registry ??= $this->createStub(IAgentRuntimeRegistry::class);
		if (!$providedRegistry) {
			$registry->method('hasRuntime')->willReturnCallback(static fn(string $id): bool => in_array($id, ['alpha', 'beta'], true));
			$registry->method('getConfigFormService')->willReturn($form);
			$registry->method('getRuntimeOptions')->willReturn([
				['id' => 'alpha', 'label' => 'Alpha', 'description' => 'Primary runtime']
			]);
		}
		$selector = $this->createStub(IAgentRuntimeSelector::class);
		$selector->method('selectRuntimeId')->willReturn($runtimeId);
		$selector->method('getDefaultRuntimeId')->willReturn('alpha');
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('en');
		return new CompositeAgentConfigFormService($request, $registry, $selector, $language);
	}
}

require_once __DIR__ . '/RuntimeFormFakeView.php';
