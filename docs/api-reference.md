# AssistantRuntime API Reference

## Purpose

This reference lists the concrete shared runtime services in the current package and their public methods.

### `AssistantRuntimePlugin`

Source: `src/AssistantRuntimePlugin.php`.

* `__construct(private readonly IContainer $container)`
* `static getName() : string`
* `init()`

### `AiServiceDashboardDisplay`

Source: `src/Display/AiServiceDashboardDisplay.php`.

* `__construct(private readonly IMvcView $view, private readonly IConfiguration $config, private readonly IRequest $request, private readonly IClassMap $classmap)`
* `static getName() : string`
* `setData($data)`
* `getOutput(string $out = 'html', bool $final = false) : string`
* `getHelp() : string`

### `AgentContextProfileService`

Source: `src/Service/AgentContextProfileService.php`.

* `__construct(private readonly IClassMap $classMap)`
* `static getName() : string`
* `getOptions() : array`
* `hasProfile(string $profileId) : bool`
* `build(string $profileId, AgentExecutionRequest $request) : AgentContextProfileResult`

### `AgentEventDispatcher`

Source: `src/Service/AgentEventDispatcher.php`.

* `static fromContext(IAgentContext $context) : ?IAgentEventSink`
* `static emit(?IAgentEventSink $eventSink, string $event, array $payload) : void`

### `AgentRuntimeRegistry`

Source: `src/Service/AgentRuntimeRegistry.php`.

* `__construct(private readonly IClassMap $classMap)`
* `static getName() : string`
* `getRuntimeIds() : array`
* `getRuntimeOptions() : array`
* `hasRuntime(string $runtimeId) : bool`
* `getExecutionService(string $runtimeId) : IAgentRuntimeService`
* `getConfigFormService(string $runtimeId) : IAgentRuntimeConfigFormService`
* `getConversationService(string $runtimeId) : IAgentConversationRuntimeService`
* `getTextTaskService(string $runtimeId) : IAgentTextTaskRuntimeService`

### `AgentToolProfileService`

Source: `src/Service/AgentToolProfileService.php`.

* `__construct(private readonly IClassMap $classMap)`
* `static getName() : string`
* `getOptions() : array`
* `hasProfile(string $profileId) : bool`
* `resolve(array $profileIds, AgentExecutionRequest $request) : IAgentToolSet`

### `CollectingAgentEventSink`

Source: `src/Service/CollectingAgentEventSink.php`.

* `emit(AgentExecutionEvent $event) : void`
* `isCancelled() : bool`
* `getEvents() : array`

### `CompositeAgentConfigFormService`

Source: `src/Service/CompositeAgentConfigFormService.php`.

* `__construct(private readonly IRequest $request, private readonly IAgentRuntimeRegistry $runtimeRegistry, private readonly IAgentRuntimeSelector $runtimeSelector, private readonly ILanguage $language)`
* `static getName() : string`
* `getDefaultSettings() : array`
* `normalizeSettings(array $settings) : array`
* `getPostedSettings(array &$errors, ?string $runtimeId = null) : array`
* `getPostedViewValues(?string $runtimeId = null) : array`
* `settingsToViewValues(array $settings) : array`
* `assignViewData(IMvcView $view, array $settings, array $options = []) : void`

### `CompositeAgentToolSet`

Source: `src/Service/CompositeAgentToolSet.php`.

* `__construct(array $sets = [])`
* `getCatalog() : AgentCapabilityCatalog`
* `getWarnings() : array`
* `execute(string $callId, string $toolName, array $arguments, array $metadata = []) : AgentToolResult`
* `prepareSuspension(string $callId, string $toolName, array $arguments, array $metadata = []) : ?AgentSuspension`
* `resumeSuspension(AgentSuspension $suspension, AgentInteractionResponse $response, array $metadata = []) : AgentToolResult`

### `PreferredAgentRuntimeSelector`

Source: `src/Service/PreferredAgentRuntimeSelector.php`.

* `__construct(IAgentRuntimeRegistry $runtimeRegistry, private readonly string $preferredRuntimeId)`
* `static getName() : string`
* `getDefaultRuntimeId() : string`

### `RoutingAgentConversationService`

Source: `src/Service/RoutingAgentConversationService.php`.

* `__construct(private readonly IAgentRuntimeRegistry $runtimeRegistry, private readonly IAgentRuntimeSelector $runtimeSelector)`
* `static getName() : string`
* `getState(AgentConversationRequest $request, string $conversationId = '') : AgentConversationState`
* `createConversation(AgentConversationRequest $request, ?string $conversationId = null, string $title = '', string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY, string $openingMessage = '') : AgentConversationState`
* `activateConversation(AgentConversationRequest $request, string $conversationId) : AgentConversationState`
* `renameConversation(AgentConversationRequest $request, string $conversationId, string $title, string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL) : AgentConversationState`
* `deleteConversation(AgentConversationRequest $request, string $conversationId) : AgentConversationState`
* `appendMessage(AgentConversationRequest $request, string $conversationId, array $message) : AgentConversationState`
* `touchConversation(AgentConversationRequest $request, string $conversationId) : AgentConversationState`

### `RoutingAgentExecutionService`

Source: `src/Service/RoutingAgentExecutionService.php`.

* `__construct(private readonly IAgentRuntimeRegistry $runtimeRegistry, private readonly IAgentRuntimeSelector $runtimeSelector)`
* `static getName() : string`
* `execute(AgentExecutionRequest $request, ?IAgentEventSink $eventSink = null) : AgentExecutionResult`

### `RoutingAgentTextTaskService`

Source: `src/Service/RoutingAgentTextTaskService.php`.

* `__construct(private readonly IAgentRuntimeRegistry $runtimeRegistry, private readonly IAgentRuntimeSelector $runtimeSelector)`
* `static getName() : string`
* `executeTextTask(AgentTextTaskRequest $request) : AgentTextTaskResult`

### `StateStoreAgentSuspensionRepository`

Source: `src/Service/StateStoreAgentSuspensionRepository.php`.

* `__construct(private readonly IStateStore $stateStore, private readonly int $claimTtlSeconds = 30, private readonly int $replayTtlSeconds = 86400)`
* `create(AgentSuspension $suspension, int $ttlSeconds) : string`
* `findPending(string $scopeId) : ?AgentSuspensionState`
* `claim(string $resumeHandle) : AgentSuspensionClaim`
* `release(AgentSuspensionClaim $claim) : void`
* `consume(AgentSuspensionClaim $claim) : void`

### `StrictAgentRuntimeSelector`

Source: `src/Service/StrictAgentRuntimeSelector.php`.

* `__construct(protected readonly IAgentRuntimeRegistry $runtimeRegistry)`
* `static getName() : string`
* `selectRuntimeId(array $agentConfiguration) : string`
* `getDefaultRuntimeId() : string`
