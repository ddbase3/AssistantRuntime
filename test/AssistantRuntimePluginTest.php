<?php declare(strict_types=1);

namespace AssistantRuntime\Test;

use AssistantFoundation\Api\IAgentConfigFormService;
use AssistantFoundation\Api\IAgentContextProfileService;
use AssistantFoundation\Api\IAgentConversationService;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Api\IAgentRuntimeRegistry;
use AssistantFoundation\Api\IAgentRuntimeSelector;
use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Api\IAgentTextTaskService;
use AssistantFoundation\Api\IAgentToolProfileService;
use AssistantRuntime\AssistantRuntimePlugin;
use AssistantRuntime\Service\AgentContextProfileService;
use AssistantRuntime\Service\AgentRuntimeRegistry;
use AssistantRuntime\Service\AgentToolProfileService;
use AssistantRuntime\Service\CompositeAgentConfigFormService;
use AssistantRuntime\Service\RoutingAgentConversationService;
use AssistantRuntime\Service\RoutingAgentExecutionService;
use AssistantRuntime\Service\RoutingAgentTextTaskService;
use AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use AssistantRuntime\Service\StrictAgentRuntimeSelector;
use Base3\Api\IContainer;
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
