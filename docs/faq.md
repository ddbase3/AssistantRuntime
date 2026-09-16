# AssistantRuntime FAQ

## What is AssistantRuntime?

AssistantRuntime is the shared runtime-composition layer between consumers and installed assistant runtimes.

It provides discovery, validation, runtime selection, execution routing, conversation routing, text-task routing, context-profile aggregation, tool-profile aggregation, configuration-form composition, durable suspension storage, event-sink helpers, and a shared AI service dashboard.

## Does AssistantRuntime implement an agent engine?

No. It does not implement planning, agent stages, provider adapters, concrete tools, or runtime-specific orchestration.

Installed runtime plugins implement the runtime contracts from AssistantFoundation. AssistantRuntime discovers them and routes requests to the selected runtime.

## How are runtimes discovered?

`AgentRuntimeRegistry` discovers implementations through `IClassMap`. A runtime exposes a stable runtime identifier through the AssistantFoundation runtime contracts.

The registry validates runtime identifiers and rejects duplicate runtime registrations.

## How is a runtime selected?

The default `StrictAgentRuntimeSelector` uses the `agent_runtime` value from the agent configuration.

If an explicit runtime identifier is present, it must exist. AssistantRuntime does not silently route to another runtime when that configured runtime is missing.

## What happens when no runtime is configured?

If exactly one runtime is installed, it can become the default. If several runtimes are installed, the selector considers their declared default priority. If the result is ambiguous, configuration must store `agent_runtime` explicitly.

`PreferredAgentRuntimeSelector` can provide a deliberate preferred runtime while retaining strict validation.

## Which operations are routed through AssistantRuntime?

The component currently provides three parallel routing services:

- `RoutingAgentExecutionService` for normal agent execution,
- `RoutingAgentConversationService` for conversation lifecycle operations,
- `RoutingAgentTextTaskService` for isolated text tasks.

All three use the same runtime selector and runtime registry.

## Does AssistantRuntime store conversations?

No. Conversation operations are delegated to the selected runtime through `IAgentConversationRuntimeService`.

The runtime implementation decides whether conversations are persisted, where they are stored, and which lifecycle rules apply.

## What are context profiles?

`AgentContextProfileService` discovers all `IAgentContextProfileProvider` implementations and exposes one aggregate profile namespace.

A selected profile is built by the provider that owns it. AssistantRuntime does not copy or persist the profile definition.

## What are tool profiles?

`AgentToolProfileService` discovers `IAgentToolProfileProvider` implementations and resolves selected profile identifiers to run-local tool sets.

When several tool sets are active, `CompositeAgentToolSet` combines their capability catalogs and routes each execution to the set that owns the requested tool.

## How are duplicate profile or tool names handled?

AssistantRuntime rejects ambiguous identifiers. Duplicate context-profile IDs, tool-profile IDs, provider IDs, and effective tool names result in errors instead of last-one-wins behavior.

## Does AssistantRuntime execute concrete tools itself?

No. Tool execution remains in provider-owned tool sets. `CompositeAgentToolSet` only routes calls to the owning set and normalizes the aggregate catalog behavior.

## How are approval-gated tool calls handled?

Confirmable tool sets can produce an `AgentSuspension` before a mutation is executed. AssistantRuntime provides a shared `IAgentSuspensionRepository` implementation that persists the server-owned suspension and returns an opaque resume handle.

The reviewed call is restored from server-owned state. It is not reconstructed from client-supplied tool arguments.

## Where are suspensions stored?

The default `StateStoreAgentSuspensionRepository` stores suspensions in the configured BASE3 `IStateStore`.

It does not create a separate database or private storage system. The physical storage backend depends on the `IStateStore` implementation selected by the application.

## Which suspension keys are used?

The repository uses internal keys under these prefixes:

```text
assistant.agent.suspension.state.
assistant.agent.suspension.claim.
assistant.agent.suspension.history.
```

The scope portion of the key is derived from a SHA-256 hash of the scope identifier rather than using the raw scope identifier directly.

## Are suspension resume handles reusable?

No. The repository implements claim leasing and replay protection.

A handle can be claimed temporarily, released when processing can safely be retried, or consumed after completion. Consumed handles receive a replay marker so the same approval response cannot execute the action again.

## What is stored in suspension history?

The default repository stores a compact history entry containing lifecycle information, interaction request titles and messages, summaries, risk values, timestamps, and resolution outcome information.

For resolved responses, the history deliberately omits response input, notes, and response metadata. The active suspension can contain richer server-owned state until it expires or is consumed.

## Does suspension history expire automatically?

Active suspension state uses the TTL supplied when the suspension is created. Claim leases and consumed-handle replay markers also use TTLs.

The current history record is written to `IStateStore` without a TTL. Installations that require bounded retention for suspension history must define an appropriate cleanup or storage policy at the operational boundary.

## How are agent configuration forms composed?

`CompositeAgentConfigFormService` owns the shared runtime selector and delegates runtime-specific settings to the selected runtime form service.

It reads posted runtime selection through `IRequest`, validates the runtime identifier, and delegates normalization and form parsing to the corresponding runtime.

## Does AssistantRuntime persist agent configuration?

No. The form service returns normalized settings and view values. Persistence belongs to the calling administration or host component.

## What are agent execution events?

`AgentEventDispatcher` emits typed `AgentExecutionEvent` values to an optional run-scoped `IAgentEventSink` stored in the active agent context.

`CollectingAgentEventSink` keeps emitted events only in memory for synchronous diagnostics or tests. It is not a durable event log.

## Does the collecting event sink support cancellation?

The collecting sink itself always reports that it is not cancelled. The shared event-sink contract supports cancellation, and other sink implementations can use that signal at safe runtime boundaries.

## What does the AI service dashboard do?

`AiServiceDashboardDisplay` inspects configured service sections that have discoverable `IAiServiceTester` implementations. It can render grouped service status information and run a service-specific test action.

The dashboard masks configured credentials and shortens endpoints in its displayed result.

## Does the dashboard expose full API keys?

No. The display only returns a shortened representation of recognized credential fields such as API keys, tokens, secrets, or keys.

The full configured service section is passed to the selected `IAiServiceTester` when the user explicitly runs a test. The tester implementation decides whether that test performs a remote request.

## Does AssistantRuntime send AI prompts to external providers?

Not directly. Routing services delegate execution to the selected runtime. Any external provider communication happens inside that runtime or its configured provider implementations.

## Does AssistantRuntime log prompts or responses?

There is no built-in durable prompt or response log in this component. The collecting event sink is memory-only.

A selected runtime, provider, host, or logging component can introduce additional logging and must document it separately.

## What data can AssistantRuntime process from a privacy perspective?

Depending on the caller and installed runtime, the routing layer can receive agent configuration, inputs, runtime context, conversation operations, text-task requests, profile selections, tool arguments and results, interaction requests, and event payloads.

The default suspension repository persists a subset of approval and interaction state in `IStateStore`. The AI service dashboard also reads configured endpoint and credential values for display masking and service tests. See [PRIVACY.md](../PRIVACY.md) for the technical data-processing details.

## Which services does the plugin register by default?

`AssistantRuntimePlugin` registers replaceable shared defaults for context profiles, tool profiles, the suspension repository, runtime registry, strict selector, the three routing services, and composite configuration forms.

The registrations use `NOOVERWRITE` so project composition can deliberately replace them.

## Which license applies?

AssistantRuntime is licensed under GPL-3.0. See `LICENSE` for the complete license text.
