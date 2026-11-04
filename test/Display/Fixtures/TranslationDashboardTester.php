<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Display\Fixtures;

use AssistantFoundation\Api\IAiServiceTester;

final class TranslationDashboardTester implements IAiServiceTester {

	public static function getType(): string {
		return 'deepl';
	}

	public function test(array $config): array {
		return ['ok' => false, 'apikey_valid' => false, 'tested' => 'deepl'];
	}
}
