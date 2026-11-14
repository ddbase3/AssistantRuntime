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

namespace Base3\AssistantRuntime\Test\Service;

use Base3\AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\AssistantFoundation\Api\IAgentRuntimeSelector;
use Base3\AssistantRuntime\Service\CompositeAgentConfigFormService;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Language\Api\ILanguage;
use PHPUnit\Framework\TestCase;

final class RuntimeFormFakeView implements IMvcView {

	private array $assigned = [];

	public function setPath(string $path = '.'): void {}

	public function assign(string $key, $value): void {
		$this->assigned[$key] = $value;
	}

	public function setTemplate(string $template = 'default'): void {}

	public function loadTemplate(): string {
		return '';
	}

	public function loadBricks(string $set, string $language = ''): void {}

	public function getBricks(string $set): ?array {
		return null;
	}

	public function getAssigned(string $key): mixed {
		return $this->assigned[$key] ?? null;
	}
}
