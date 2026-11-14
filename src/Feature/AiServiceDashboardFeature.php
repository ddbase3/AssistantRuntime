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

namespace Base3\AssistantRuntime\Feature;

use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Permission;

/** Protects direct AI service dashboard access without publishing a settings tab. */
final class AiServiceDashboardFeature implements IFeature {

	public function __construct(private readonly ITranslation $translation) {}

	public static function getName(): string {
		return 'aiservicedashboardfeature';
	}

	public function getLabel(): string {
		return $this->translation->translate('Settings', self::getName(), 'label');
	}

	public function getDescription(): string {
		return $this->translation->translate('Settings', self::getName(), 'description');
	}

	public function getPermissions(): array {
		return [Permission::for('feature', self::getName())];
	}

	public function getDefaultAudience(): array {
		return ['group' => 'administration'];
	}
}
