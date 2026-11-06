<?php declare(strict_types=1);

namespace AssistantRuntime\Feature;

use Base3\Authorization\Api\IFeature;
use Base3\Translation\Api\ITranslation;
use Base3\Usermanager\Permission;

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
