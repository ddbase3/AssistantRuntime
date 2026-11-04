<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

class EmptyIdRuntimeExecutionFixture extends AlphaRuntimeExecutionFixture {

	public static function getName(): string { return 'emptyidruntimeexecutionfixture'; }
	public static function getRuntimeId(): string { return ' !!! '; }
}
