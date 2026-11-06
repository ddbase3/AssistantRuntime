<?php declare(strict_types=1);

namespace AssistantRuntime\Test\Feature;

use AssistantRuntime\Feature\AiServiceDashboardFeature;
use Base3\Authorization\Api\IFeature;
use Base3\Translation\Api\ITranslation;
use PHPUnit\Framework\TestCase;
use UiFoundation\Api\ISettingsTab;

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
