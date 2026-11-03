<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service;

use PHPUnit\Framework\TestCase;

final class AgentRuntimeTemplateRenderer {

	public function __construct(public array $_) {}

	public function render(string $template): string {
		ob_start();
		include $template;
		return (string)ob_get_clean();
	}
}
