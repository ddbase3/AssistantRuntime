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

namespace Base3\AssistantRuntime\Test;

use Base3\AssistantFoundation\Api\IAgentConfigFormService;
use Base3\AssistantFoundation\Api\IAgentContextProfileService;
use Base3\AssistantFoundation\Api\IAgentConversationService;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Api\IAgentRuntimeRegistry;
use Base3\AssistantFoundation\Api\IAgentRuntimeSelector;
use Base3\AssistantFoundation\Api\IAgentSuspensionRepository;
use Base3\AssistantFoundation\Api\IAgentTextTaskService;
use Base3\AssistantFoundation\Api\IAgentToolProfileService;
use Base3\AssistantRuntime\AssistantRuntimePlugin;
use Base3\AssistantRuntime\Service\AgentContextProfileService;
use Base3\AssistantRuntime\Service\AgentRuntimeRegistry;
use Base3\AssistantRuntime\Service\AgentToolProfileService;
use Base3\AssistantRuntime\Service\CompositeAgentConfigFormService;
use Base3\AssistantRuntime\Service\RoutingAgentConversationService;
use Base3\AssistantRuntime\Service\RoutingAgentExecutionService;
use Base3\AssistantRuntime\Service\RoutingAgentTextTaskService;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\AssistantRuntime\Service\StrictAgentRuntimeSelector;
use Base3\CoreFoundation\Api\IContainer;
use PHPUnit\Framework\TestCase;

final class AssistantRuntimePluginTest extends TestCase {

	public function testPluginRegistersTheSharedRuntimeOwnerAndInterfacesWithoutOverwriting(): void {
		$registered = [];
		$container = $this->createStub(IContainer::class);
		$container->method('set')->willReturnCallback(
			static function(string $name, mixed $definition, int $flags) use ($container, &$registered): IContainer {
				$registered[$name] = ['definition' => $definition, 'flags' => $flags];
				return $container;
			}
		);
		$plugin = new AssistantRuntimePlugin($container);
		$plugin->init();

		$expected = [
			AssistantRuntimePlugin::getName(),
			AgentContextProfileService::class, IAgentContextProfileService::class,
			AgentToolProfileService::class, IAgentToolProfileService::class,
			StateStoreAgentSuspensionRepository::class, IAgentSuspensionRepository::class,
			AgentRuntimeRegistry::class, IAgentRuntimeRegistry::class,
			StrictAgentRuntimeSelector::class, IAgentRuntimeSelector::class,
			RoutingAgentExecutionService::class, IAgentExecutionService::class,
			RoutingAgentConversationService::class, IAgentConversationService::class,
			RoutingAgentTextTaskService::class, IAgentTextTaskService::class,
			CompositeAgentConfigFormService::class, IAgentConfigFormService::class
		];
		$this->assertSame($expected, array_keys($registered));
		$this->assertSame($plugin, $registered[AssistantRuntimePlugin::getName()]['definition']);
		foreach ($registered as $name => $registration) {
			$this->assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $registration['flags']);
			if ($name !== AssistantRuntimePlugin::getName()) {
				$this->assertInstanceOf(\Closure::class, $registration['definition']);
			}
		}
	}
}
