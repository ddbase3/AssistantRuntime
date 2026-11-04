<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Service\Fixtures;

class BetaRuntimeFormFixture extends AlphaRuntimeFormFixture {

	public static function getName(): string { return 'betaruntimeformfixture'; }
	public static function getRuntimeId(): string { return ' beta '; }
}
