<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Display\Fixtures;

use AssistantFoundation\Api\IAiServiceTester;

final class ApiKeyDashboardTester implements IAiServiceTester {

	public static function getType(): string {
		return 'openai';
	}

	public function test(array $config): array {
		return [
			'ok' => true,
			'apikey_valid' => true,
			'config_received' => $config
		];
	}
}
