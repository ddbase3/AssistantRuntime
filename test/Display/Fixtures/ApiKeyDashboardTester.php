<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 *
 * AssistantRuntime provides the shared execution routing for BASE3 agents.
 * It composes runtime registries, sinks, selectors and configuration services.
 * Agent contracts remain in AssistantFoundation and runtime plugins.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/AssistantRuntime
 **********************************************************************/

namespace Base3\AssistantRuntime\Test\Display\Fixtures;

use Base3\AssistantFoundation\Api\IAiServiceTester;

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
