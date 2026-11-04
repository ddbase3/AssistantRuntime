<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Display;

use AssistantRuntime\Display\AiServiceDashboardDisplay;
use AssistantRuntime\Test\Display\Fixtures\ApiKeyDashboardTester;
use AssistantRuntime\Test\Display\Fixtures\TranslationDashboardTester;
use Base3\Api\IClassMap;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Configuration\Api\IConfiguration;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Session\Api\ISession;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class AiServiceDashboardDisplayBehaviorTest extends TestCase {

	public function testTechnicalIdentityAndHelpDescribeExistingDisplay(): void {
		$this->assertSame('aiservicedashboarddisplay', AiServiceDashboardDisplay::getName());
		$this->assertStringContainsString('AI services', $this->display()->getHelp());
	}

	public function testShortCredentialsNeverContainWholeLongSecret(): void {
		$display = $this->display();
		$this->assertSame('', $this->invoke($display, 'shortApiKey', ''));
		$this->assertSame('ab****ab', $this->invoke($display, 'shortApiKey', 'ab'));
		$this->assertSame('ab****yz', $this->invoke($display, 'shortApiKey', 'ab1234yz'));
		$this->assertSame('abcd******wxyz', $this->invoke($display, 'shortApiKey', 'abcd-secret-wxyz'));
		$this->assertStringNotContainsString('secret', $this->invoke($display, 'shortApiKey', 'abcd-secret-wxyz'));
	}

	public function testShortEndpointUsesHostAndDoesNotExposePath(): void {
		$display = $this->display();
		$this->assertSame('', $this->invoke($display, 'shortEndpoint', ''));
		$this->assertSame('api.exam...example.test', $this->invoke($display, 'shortEndpoint', 'https://api.example.test/private/secret'));
		$this->assertSame('server...server', $this->invoke($display, 'shortEndpoint', 'http://server'));
	}

	public function testFirstCredentialIgnoresNonStringAndEmptyEntries(): void {
		$display = $this->display();
		$this->assertSame('usable', $this->invoke($display, 'pickFirstValue', ['apikey' => null, 'token' => 42, 'secret' => 'usable'], ['apikey', 'token', 'secret']));
		$this->assertSame('first', $this->invoke($display, 'pickFirstValue', ['token' => 'first', 'secret' => 'last'], ['token', 'secret']));
		$this->assertSame('', $this->invoke($display, 'pickFirstValue', ['token' => '', 'secret' => false], ['token', 'secret']));
	}

	public function testKnownAndUnknownGroupNamesHaveStableLabels(): void {
		$display = $this->display();
		$this->assertSame('LLM', $this->invoke($display, 'prettyGroupName', 'llm'));
		$this->assertSame('Embeddings', $this->invoke($display, 'prettyGroupName', 'embedding'));
		$this->assertSame('Client Profiles', $this->invoke($display, 'prettyGroupName', 'client_profiles'));
		$this->assertSame('OpenAI', $this->invoke($display, 'prettyName', 'openai'));
		$this->assertSame('Internal Search', $this->invoke($display, 'prettyName', 'internal_search'));
	}

	public function testTranslationsUseLoadedScalarValuesAndFallbackForEmpty(): void {
		$view = $this->createStub(IMvcView::class);
		$view->method('getBricks')->willReturn(['dashboard_group_embeddings' => ' Einbettungen ', 'dashboard_group_services' => '  ', 'ignored' => []]);
		$display = $this->display(view: $view);
		$this->invoke($display, 'prepareTranslations');
		$this->assertSame('Einbettungen', $this->invoke($display, 'prettyGroupName', 'embedding'));
		$this->assertSame('Services', $this->invoke($display, 'translate', 'dashboard_group_services', 'Services'));
		$this->assertSame('default', $this->invoke($display, 'translate', 'missing', 'default'));
	}

	public function testNonArrayTranslationsFallBackToEmptyDictionary(): void {
		$view = $this->createStub(IMvcView::class);
		$view->method('getBricks')->willReturn(null);
		$display = $this->display(view: $view);
		$this->invoke($display, 'prepareTranslations');
		$this->assertSame('Translation', $this->invoke($display, 'prettyGroupName', 'translation'));
	}

	public function testInvalidConfigurationProducesNoServiceGroups(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn('invalid');
		$display = $this->display(config: $config);
		$this->assertSame([], $this->invoke($display, 'collectServicesGrouped'));
	}

	public function testFlatConfigurationOnlyExposesServicesWithTesters(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn([
			'openai' => ['endpoint' => 'https://api.example.test/private/path', 'apikey' => 'verylongsecrettoken'],
			'deepl' => ['token' => 'short'], 'other' => ['apikey' => 'hidden']
		]);
		$display = $this->display(config: $config, testers: [new ApiKeyDashboardTester(), new TranslationDashboardTester()]);
		$groups = $this->invoke($display, 'collectServicesGrouped');
		$this->assertSame('services', $groups[0]['id']);
		$this->assertSame(['openai', 'deepl'], array_column($groups[0]['services'], 'id'));
		$this->assertSame('very******oken', $groups[0]['services'][0]['apikeyShort']);
		$this->assertSame('sh****rt', $groups[0]['services'][1]['apikeyShort']);
		$this->assertSame('api.exam...example.test', $groups[0]['services'][0]['endpointShort']);
		$this->assertTrue($groups[0]['services'][0]['hasTester']);
		$this->assertStringNotContainsString('verylongsecrettoken', json_encode($groups));
	}

	public function testGroupedConfigurationSkipsInvalidAndUntestedEntries(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn([
			'services' => [
				'llm' => ['openai', 'missing', '', 'unknown', 'deepl'],
				'ignored' => 'not-an-array',
				'parser' => ['invalid', 'missing'],
				'translation' => ['deepl']
			],
			'openai' => ['apikey' => 'first'],
			'deepl' => ['token' => 'second'],
			'unknown' => ['apikey' => 'unused'],
			'invalid' => 'not-an-array'
		]);
		$display = $this->display(config: $config, testers: [new ApiKeyDashboardTester(), new TranslationDashboardTester()]);
		$groups = $this->invoke($display, 'collectServicesGrouped');
		$this->assertSame(['llm', 'translation'], array_column($groups, 'id'));
		$this->assertSame(['openai', 'deepl'], array_column($groups[0]['services'], 'id'));
		$this->assertSame(['deepl'], array_column($groups[1]['services'], 'id'));
		$this->assertSame('Translation', $groups[1]['name']);
	}

	public function testUnknownGroupWithOnlyInvalidServicesIsOmitted(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn(['services' => ['other_group' => ['missing', '']]]);
		$this->assertSame([], $this->invoke($this->display(config: $config), 'collectServicesGrouped'));
	}

	public function testServiceTesterReceivesOnlyItsConfiguredSection(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn(['openai' => ['apikey' => 'test-token'], 'unrelated' => ['private' => true]]);
		$display = $this->display(config: $config, testers: [new ApiKeyDashboardTester()]);
		$this->assertSame([
			'ok' => true,
			'apikey_valid' => true,
			'config_received' => ['apikey' => 'test-token']
		], $this->invoke($display, 'runServiceTest', 'openai'));
	}

	public function testInvalidServiceRequestNeverCallsTester(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn(['openai' => ['apikey' => 'private'], 'other' => ['token' => 'x']]);
		$display = $this->display(config: $config);
		$this->assertSame('Missing service', $this->invoke($display, 'runServiceTest', '')['message']);
		$this->assertSame('Unknown service', $this->invoke($display, 'runServiceTest', 'missing')['message']);
		$this->assertSame('No tester available', $this->invoke($display, 'runServiceTest', 'other')['message']);
	}

	public function testCsrfTokenRequiresStartedSession(): void {
		$session = $this->createStub(ISession::class);
		$session->method('started')->willReturn(false);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('active session');
		$this->invoke($this->display(session: $session), 'csrfToken');
	}

	public function testCsrfReusesExistingValidToken(): void {
		$session = $this->createMock(ISession::class);
		$token = str_repeat('a', 64);
		$session->method('started')->willReturn(true);
		$session->method('get')->willReturn($token);
		$session->expects($this->never())->method('set');
		$this->assertSame($token, $this->invoke($this->display(session: $session), 'csrfToken'));
	}

	public function testCsrfReplacesMissingAndMalformedTokens(): void {
		foreach ([null, false, 'short'] as $existing) {
			$generated = null;
			$session = $this->createMock(ISession::class);
			$session->method('started')->willReturn(true);
			$session->method('get')->willReturn($existing);
			$session->expects($this->once())->method('set')->willReturnCallback(
				static function(string $key, mixed $value) use (&$generated): void {
					$generated = $value;
				}
			);
			$token = $this->invoke($this->display(session: $session), 'csrfToken');
			$this->assertSame($generated, $token);
			$this->assertSame(64, strlen($token));
			$this->assertSame(1, preg_match('/^[a-f0-9]{64}$/', $token));
		}
	}

	public function testCsrfValidationRequiresMatchingStrings(): void {
		$session = $this->createStub(ISession::class);
		$session->method('get')->willReturn(str_repeat('a', 64));
		$request = $this->createStub(IRequest::class);
		$request->method('post')->willReturn(str_repeat('a', 64));
		$this->assertTrue($this->invoke($this->display(request: $request, session: $session), 'validCsrfToken'));
	}

	public function testCsrfValidationRejectsMissingAndNonMatchingTokens(): void {
		foreach ([false, str_repeat('b', 64), null, []] as $posted) {
			$session = $this->createStub(ISession::class);
			$session->method('get')->willReturn(str_repeat('a', 64));
			$request = $this->createStub(IRequest::class);
			$request->method('post')->willReturn($posted);
			$this->assertFalse($this->invoke($this->display(request: $request, session: $session), 'validCsrfToken'));
		}
	}

	private function display(
		?IMvcView $view = null,
		?IConfiguration $config = null,
		?IRequest $request = null,
		?ISession $session = null,
		array $testers = []
	): AiServiceDashboardDisplay {
		$view ??= $this->createStub(IMvcView::class);
		$config ??= $this->createStub(IConfiguration::class);
		$request ??= $this->createStub(IRequest::class);
		$session ??= $this->createStub(ISession::class);
		$classmap = $this->createStub(IClassMap::class);
		$classmap->method('getInstances')->willReturn($testers);
		return new AiServiceDashboardDisplay(
			$view,
			$config,
			$request,
			$classmap,
			$this->createStub(IAuthorizationService::class),
			$session,
			$this->createStub(ILinkTargetService::class)
		);
	}

	private function invoke(AiServiceDashboardDisplay $display, string $method, mixed ...$args): mixed {
		return (new ReflectionMethod(AiServiceDashboardDisplay::class, $method))->invoke($display, ...$args);
	}
}
require_once __DIR__ . '/Fixtures/ApiKeyDashboardTester.php';
require_once __DIR__ . '/Fixtures/TranslationDashboardTester.php';
