<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of AssistantRuntime for BASE3 Framework.
 *
 * AssistantRuntime provides shared runtime composition for assistants and
 * agent implementations. Contracts remain in AssistantFoundation while
 * concrete registries, routers, sinks and configuration forms live here.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/assistantruntime
 * https://github.com/ddbase3/AssistantRuntime
 **********************************************************************/

namespace AssistantRuntime\Service;

use AssistantFoundation\Api\IAgentRuntimeRegistry;

final class PreferredAgentRuntimeSelector extends StrictAgentRuntimeSelector {

	public function __construct(
		IAgentRuntimeRegistry $runtimeRegistry,
		private readonly string $preferredRuntimeId
	) {
		parent::__construct($runtimeRegistry);
	}

	public static function getName(): string {
		return 'preferredagentruntimeselector';
	}

	public function getDefaultRuntimeId(): string {
		$runtimeId = $this->normalizeRuntimeId($this->preferredRuntimeId);
		if ($runtimeId !== '' && $this->runtimeRegistry->hasRuntime($runtimeId)) {
			return $runtimeId;
		}

		return parent::getDefaultRuntimeId();
	}
}
