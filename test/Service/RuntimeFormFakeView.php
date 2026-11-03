<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantRuntime\Service\CompositeAgentConfigFormService;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Language\Api\ILanguage;
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
