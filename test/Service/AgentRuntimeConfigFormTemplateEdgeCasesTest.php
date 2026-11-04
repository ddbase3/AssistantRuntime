<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use PHPUnit\Framework\TestCase;

final class AgentRuntimeConfigFormTemplateEdgeCasesTest extends TestCase {

	public function testRuntimeOptionsEscapeUntrustedDescriptionsAndIdentifiers(): void {
		$html = $this->render([
			'form_id' => 'my-form',
			'selected_runtime' => 'alpha',
			'show_runtime_selector' => true,
			'runtime_options' => [
				['id' => 'alpha', 'label' => '<Alpha>', 'description' => 'quoted "description"'],
				['id' => '', 'label' => 'invalid']
			]
		]);
		$this->assertStringContainsString('id="my-form_runtime_config"', $html);
		$this->assertStringContainsString('&lt;Alpha&gt;', $html);
		$this->assertStringContainsString('quoted &quot;description&quot;', $html);
		$this->assertStringContainsString('value="alpha"', $html);
		$this->assertStringNotContainsString('>invalid</option>', $html);
	}

	public function testInactiveRuntimeUsesEmptyHiddenInputAndDisablesAllSections(): void {
		$template = tempnam(sys_get_temp_dir(), 'runtime-section-');
		$this->assertNotEmpty($template);
		file_put_contents($template, 'selected');
		try {
			$html = $this->render([
				'selected_runtime' => 'alpha',
				'show_runtime_selector' => false,
				'runtime_active' => false,
				'runtime_sections' => [['id' => 'alpha', 'template' => $template, 'form' => []]]
			]);
			$this->assertStringContainsString('name="agent_runtime" value=""', $html);
			$this->assertStringContainsString('data-agent-runtime-section="alpha" hidden disabled', $html);
		} finally {
			@unlink($template);
		}
	}

	public function testSelectedRuntimeOnlyEnablesItsOwnSection(): void {
		$template = tempnam(sys_get_temp_dir(), 'runtime-section-');
		$this->assertNotEmpty($template);
		file_put_contents($template, 'rendered');
		try {
			$html = $this->render([
				'selected_runtime' => 'beta',
				'show_runtime_selector' => false,
				'runtime_active' => true,
				'runtime_sections' => [
					['id' => 'alpha', 'template' => $template, 'form' => []],
					['id' => 'beta', 'template' => $template, 'form' => []]
				]
			]);
			$this->assertStringContainsString('value="beta" data-agent-runtime-hidden="1"', $html);
			$this->assertStringContainsString('data-agent-runtime-section="alpha" hidden disabled', $html);
			$this->assertStringContainsString('data-agent-runtime-section="beta">', $html);
		} finally {
			@unlink($template);
		}
	}

	public function testMissingSubtemplateIsNotIncluded(): void {
		$html = $this->render([
			'selected_runtime' => 'alpha',
			'show_runtime_selector' => false,
			'runtime_sections' => [
				['id' => '', 'template' => '/not/used'],
				['id' => 'alpha', 'template' => ''],
				['id' => 'alpha', 'template' => '/missing/assistant-runtime-template.php']
			]
		]);
		$this->assertStringNotContainsString('<fieldset', $html);
	}

	private function render(array $form): string {
		return (new AgentRuntimeTemplateRenderer(['agent_config_form' => $form]))->render(
			dirname(__DIR__, 2) . '/tpl/Content/AgentRuntimeConfigFormSection.php'
		);
	}
}

require_once __DIR__ . '/AgentRuntimeTemplateRenderer.php';
