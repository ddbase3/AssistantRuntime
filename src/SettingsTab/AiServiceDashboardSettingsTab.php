<?php declare(strict_types=1);

namespace AssistantRuntime\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class AiServiceDashboardSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'aiservicessettingstab';
	}

	public function getDisplayName(): ?string {
		return 'aiservicedashboarddisplay';
	}

	public function getOrder(): int {
		return 10;
	}
}
