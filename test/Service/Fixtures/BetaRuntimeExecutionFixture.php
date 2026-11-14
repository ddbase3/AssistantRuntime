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

namespace Base3\AssistantRuntime\Test\Service\Fixtures;

class BetaRuntimeExecutionFixture extends AlphaRuntimeExecutionFixture {

	public static function getName(): string { return 'betaruntimeexecutionfixture'; }
	public static function getRuntimeId(): string { return ' beta '; }
	public static function getRuntimeLabel(): string { return ' Alpha '; }
	public static function getRuntimeDescription(): string { return ' Beta description '; }
	public static function getDefaultPriority(): int { return 20; }
}
