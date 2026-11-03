<?php declare(strict_types=1);

namespace AssistantRuntime\SettingsTab;

use UiFoundation\Settings\AbstractSettingsTab;

final class AiServicesSettingsTab extends AbstractSettingsTab {

	public function getOrder(): int {
		return 35;
	}
}
