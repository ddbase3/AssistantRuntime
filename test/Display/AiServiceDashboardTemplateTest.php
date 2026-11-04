<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Display;

use AssistantRuntime\Test\Service\AgentRuntimeTemplateRenderer;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AiServiceDashboardTemplateTest extends TestCase {

	#[RunInSeparateProcess]
	public function testDashboardTemplateEscapesVisibleMetadataAndSerializesRequestValues(): void {
		$renderer = new AgentRuntimeTemplateRenderer([
			'groups' => [[
				'id' => 'llm',
				'name' => '<b>Trusted group</b>',
				'services' => [[
					'id' => 'openai',
					'name' => '<img src=x>',
					'endpointShort' => 'api.example.test/<secret>',
					'apikeyShort' => 'ab******cd'
				]]
			]],
			'translations' => [
				'dashboard_api_key' => 'API-Schlüssel',
				'dashboard_test_connection' => 'Verbindung prüfen'
			],
			'testUrl' => '/?name=aiservicedashboarddisplay&out=json',
			'csrf' => '<untrusted-token>'
		]);
		$html = $renderer->render(dirname(__DIR__, 2) . '/tpl/Display/AiServiceDashboardDisplay.php');
		$this->assertStringContainsString('&lt;b&gt;Trusted group&lt;/b&gt;', $html);
		$this->assertStringContainsString('&lt;img src=x&gt;', $html);
		$this->assertStringContainsString('api.example.test/&lt;secret&gt;', $html);
		$this->assertStringContainsString('API-Schlüssel', $html);
		$this->assertStringContainsString('Verbindung prüfen', $html);
		$this->assertStringContainsString('data-service="openai"', $html);
		$this->assertStringContainsString('csrf = "\\u003Cuntrusted-token\\u003E"', $html);
		$this->assertStringContainsString('ai_service_csrf: csrf', $html);
		$this->assertStringNotContainsString('<untrusted-token>', $html);
	}
}

require_once __DIR__ . '/../Service/AgentRuntimeTemplateRenderer.php';
