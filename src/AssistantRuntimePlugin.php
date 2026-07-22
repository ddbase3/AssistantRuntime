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
 **********************************************************************/

namespace AssistantRuntime;

use AssistantFoundation\Api\IAgentConfigFormService;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantRuntime\Service\AgentRuntimeRegistry;
use AssistantRuntime\Service\CompositeAgentConfigFormService;
use AssistantRuntime\Service\RoutingAgentExecutionService;
use AssistantRuntime\Service\StrictAgentRuntimeSelector;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;

class AssistantRuntimePlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string {
		return 'assistantruntimeplugin';
	}

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(
				AgentRuntimeRegistry::class,
				fn($c) => new AgentRuntimeRegistry($c->get(IClassMap::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				IAgentRuntimeRegistry::class,
				fn($c) => $c->get(AgentRuntimeRegistry::class),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				StrictAgentRuntimeSelector::class,
				fn($c) => new StrictAgentRuntimeSelector($c->get(IAgentRuntimeRegistry::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				IAgentRuntimeSelector::class,
				fn($c) => $c->get(StrictAgentRuntimeSelector::class),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				RoutingAgentExecutionService::class,
				fn($c) => new RoutingAgentExecutionService(
					$c->get(IAgentRuntimeRegistry::class),
					$c->get(IAgentRuntimeSelector::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				IAgentExecutionService::class,
				fn($c) => $c->get(RoutingAgentExecutionService::class),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				CompositeAgentConfigFormService::class,
				fn($c) => new CompositeAgentConfigFormService(
					$c->get(IRequest::class),
					$c->get(IAgentRuntimeRegistry::class),
					$c->get(IAgentRuntimeSelector::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				IAgentConfigFormService::class,
				fn($c) => $c->get(CompositeAgentConfigFormService::class),
				IContainer::SHARED | IContainer::NOOVERWRITE
			);
	}
}
