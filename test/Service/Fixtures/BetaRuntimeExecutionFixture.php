<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

class BetaRuntimeExecutionFixture extends AlphaRuntimeExecutionFixture {

	public static function getName(): string { return 'betaruntimeexecutionfixture'; }
	public static function getRuntimeId(): string { return ' beta '; }
	public static function getRuntimeLabel(): string { return ' Alpha '; }
	public static function getRuntimeDescription(): string { return ' Beta description '; }
	public static function getDefaultPriority(): int { return 20; }
}
