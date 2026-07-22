<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use PHPUnit\Framework\TestCase;

final class AgentRuntimeConfigFormTemplateTest extends TestCase {

	public function testRuntimeSubtemplateCannotOverwriteWrapperVariables(): void {
		$runtimeTemplate = tempnam(sys_get_temp_dir(), 'base3_runtime_template_');
		if ($runtimeTemplate === false) {
			$this->fail('Temporary runtime template could not be created.');
		}

		$runtimeSource = '<?php' . "\n"
			. '$formId = \'leaked_runtime_form\';' . "\n"
			. '$selectedRuntime = \'leaked_runtime\';' . "\n"
			. '?>' . "\n"
			. '<div data-test-runtime-template="1"></div>';
		file_put_contents($runtimeTemplate, $runtimeSource);

		try {
			$renderer = new AgentRuntimeTemplateRenderer([
				'agent_config_form' => [
					'form_id' => 'expected_form',
					'selected_runtime' => 'missionbay',
					'show_runtime_selector' => false,
					'runtime_active' => true,
					'runtime_options' => [],
					'runtime_sections' => [[
						'id' => 'missionbay',
						'template' => $runtimeTemplate,
						'form' => []
					]]
				]
			]);
			$output = $renderer->render(
				dirname(__DIR__, 2) . '/tpl/Content/AgentRuntimeConfigFormSection.php'
			);

			$this->assertStringContainsString('id="expected_form_runtime_config"', $output);
			$this->assertStringContainsString('data-agent-runtime-section="missionbay"', $output);
			$this->assertStringContainsString('document.getElementById("expected_form_runtime_config")', $output);
			$this->assertStringNotContainsString('leaked_runtime_form_runtime_config', $output);
		}
		finally {
			@unlink($runtimeTemplate);
		}
	}
}

final class AgentRuntimeTemplateRenderer {

	public function __construct(public array $_) {}

	public function render(string $template): string {
		ob_start();
		include $template;
		return (string)ob_get_clean();
	}
}
