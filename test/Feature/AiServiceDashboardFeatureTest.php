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

namespace Base3\AssistantRuntime\Test\Feature;

use Base3\AssistantRuntime\Feature\AiServiceDashboardFeature;
use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use PHPUnit\Framework\TestCase;
use Base3\UiFoundation\Api\ISettingsTab;

final class AiServiceDashboardFeatureTest extends TestCase {

	public function testDashboardRemainsAFeatureWithoutPublishingSettingsNavigation(): void {
		$feature = new AiServiceDashboardFeature($this->createStub(ITranslation::class));
		self::assertInstanceOf(IFeature::class, $feature);
		self::assertNotInstanceOf(ISettingsTab::class, $feature);
		self::assertSame('aiservicedashboardfeature', $feature::getName());
		self::assertSame(['group' => 'administration'], $feature->getDefaultAudience());
		self::assertSame($feature::getName(), $feature->getPermissions()[0]->permission);
	}
}
