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
 *
 * https://base3.de/v/assistantruntime
 * https://github.com/ddbase3/AssistantRuntime
 **********************************************************************/

namespace AssistantRuntime\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Configuration\Api\IConfiguration;
use Base3\Api\IClassMap;
use AssistantFoundation\Api\IAiServiceTester;

class AiServiceDashboardDisplay implements IDisplay {

	private $data;

	private array $credentialKeys = ['apikey', 'bottoken', 'token', 'access_token', 'key', 'secret'];

	public function __construct(
		private readonly IMvcView $view,
		private readonly IConfiguration $config,
		private readonly IRequest $request,
		private readonly IClassMap $classmap
	) {}

	public static function getName(): string {
		return 'aiservicedashboarddisplay';
	}

	public function setData($data) {
		$this->data = $data;
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$action = (string)$this->request->get('action', '');

		if ($action === 'test') {
			$service = (string)$this->request->get('service', '');
			$result = $this->runServiceTest($service);
			header('Content-Type: application/json');
			return json_encode($result);
		}

		$out = (string)$this->request->get('out', $out);
		$groups = $this->collectServicesGrouped();

		if ($out === 'json') {
			header('Content-Type: application/json');
			return json_encode($groups);
		}

		$this->view->setPath(DIR_PLUGIN . 'AssistantRuntime');
		$this->view->setTemplate('Display/AiServiceDashboardDisplay.php');
		$this->view->assign('groups', $groups);

		return $this->view->loadTemplate();
	}

	public function getHelp(): string {
		return 'Displays AI services and provides API key validation tests.';
	}

	private function collectServicesGrouped(): array {
		$config = $this->config->get();
		if (!is_array($config)) return [];

		$testerMap = $this->collectTesterMap();

		$svcCfg = $config['services'] ?? null;
		if (!is_array($svcCfg)) {
			$list = $this->collectServicesFlat($config, $testerMap);
			return [[
				'id' => 'services',
				'name' => 'Services',
				'services' => $list
			]];
		}

		$groups = [];
		foreach ($svcCfg as $groupId => $serviceIds) {
			if (!is_array($serviceIds)) continue;

			$services = [];
			foreach ($serviceIds as $type) {
				$type = (string)$type;
				if ($type === '') continue;

				if (!isset($config[$type]) || !is_array($config[$type])) continue;
				if (!isset($testerMap[$type])) continue;

				$section = $config[$type];
				$endpoint = (string)($section['endpoint'] ?? '');
				$cred = $this->pickFirstValue($section, $this->credentialKeys);

				$services[] = [
					'id' => $type,
					'name' => $this->prettyName($type),
					'type' => $type,
					'endpointShort' => $this->shortEndpoint($endpoint),
					'apikeyShort' => $this->shortApiKey($cred),
					'hasTester' => true
				];
			}

			if (!$services) continue;

			$groups[] = [
				'id' => (string)$groupId,
				'name' => $this->prettyGroupName((string)$groupId),
				'services' => $services
			];
		}

		return $groups;
	}

	private function collectServicesFlat(array $config, array $testerMap): array {
		$list = [];

		foreach ($testerMap as $type => $tester) {
			if (!isset($config[$type]) || !is_array($config[$type])) continue;

			$section = $config[$type];
			$endpoint = (string)($section['endpoint'] ?? '');
			$cred = $this->pickFirstValue($section, $this->credentialKeys);

			$list[] = [
				'id' => $type,
				'name' => $this->prettyName($type),
				'type' => $type,
				'endpointShort' => $this->shortEndpoint($endpoint),
				'apikeyShort' => $this->shortApiKey($cred),
				'hasTester' => true
			];
		}

		return $list;
	}

	private function pickFirstValue(array $section, array $keys): string {
		foreach ($keys as $k) {
			if (isset($section[$k]) && is_string($section[$k]) && $section[$k] !== '') {
				return (string)$section[$k];
			}
		}
		return '';
	}

	private function collectTesterMap(): array {
		$instances = $this->classmap->getInstances([
			'interface' => IAiServiceTester::class
		]);

		$map = [];
		foreach ($instances as $tester) {
			$type = $tester::getType();
			$map[$type] = $tester;
		}
		return $map;
	}

	private function runServiceTest(string $service): array {
		if (!$service) {
			return ['ok' => false, 'apikey_valid' => false, 'message' => 'Missing service'];
		}

		$cfg = $this->config->get();
		if (!isset($cfg[$service]) || !is_array($cfg[$service])) {
			return ['ok' => false, 'apikey_valid' => false, 'message' => 'Unknown service'];
		}

		$testerMap = $this->collectTesterMap();
		if (!isset($testerMap[$service])) {
			return ['ok' => false, 'apikey_valid' => false, 'message' => 'No tester available'];
		}

		return $testerMap[$service]->test($cfg[$service]);
	}

	private function prettyGroupName(string $id): string {
		$map = [
			'llm' => 'LLM',
			'embedding' => 'Embeddings',
			'vectordb' => 'Vector DB',
			'translation' => 'Translation',
			'parser' => 'Parser',
			'communication' => 'Communication'
		];

		if (isset($map[$id])) return $map[$id];

		$id = str_replace(['-', '_'], ' ', $id);
		return ucwords($id);
	}

	private function prettyName(string $id): string {
		$map = [
			'openai' => 'OpenAI',
			'deepseek' => 'DeepSeek',
			'openrouter' => 'OpenRouter',
			'deepl' => 'DeepL',
			'base3qdrant' => 'Qdrant (Base3)',
			'base3unstructured' => 'Unstructured (Base3)',
			'qualituschat' => 'Chat (Qualitus)',
			'qualitusembedding' => 'Embedding (Qualitus)',
			'qualitusvectordb' => 'Qdrant (Qualitus)',
			'qualitusparser' => 'Docling (Qualitus)'
		];

		if (isset($map[$id])) return $map[$id];

		$id = str_replace(['-', '_'], ' ', $id);
		return ucwords($id);
	}

	private function shortEndpoint(string $url): string {
		if (!$url) return '';

		$url = preg_replace('#^https?://#', '', $url);
		$parts = explode('/', $url, 2);

		$domain = $parts[0];
		$domParts = explode('.', $domain);
		$end = implode('.', array_slice($domParts, -2));
		$prefix = substr($domain, 0, 8);

		return $prefix . '...' . $end;
	}

	private function shortApiKey(string $key): string {
		if (!$key) return '';

		if (strlen($key) <= 12) {
			return substr($key, 0, 2) . '****' . substr($key, -2);
		}

		return substr($key, 0, 4) . '******' . substr($key, -4);
	}
}
