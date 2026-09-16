# Privacy and Data Processing in AssistantRuntime

> This document describes the technical privacy-relevant behavior of the AssistantRuntime component. It is not a legal privacy notice for a specific application or installation. Concrete purposes, legal bases, recipients, retention periods, and data-subject procedures depend on the installed runtimes, storage backend, service testers, and host application.

## 1. Scope

AssistantRuntime is the shared runtime-composition layer for assistant runtimes. It discovers and validates installed runtimes, selects the configured runtime, routes execution and conversation operations, aggregates context and tool profiles, composes runtime configuration forms, stores server-owned suspensions through `IStateStore`, forwards execution events, and provides an AI service dashboard.

The component does not implement a concrete AI provider, agent engine, conversation store, retrieval backend, or concrete tool implementation.

## 2. Main data flows

AssistantRuntime can process data at several boundaries:

1. a caller supplies an execution, conversation, or text-task request;
2. the runtime selector reads the configured runtime identifier;
3. the runtime registry resolves the matching runtime implementation;
4. the routing service delegates the request to that runtime;
5. optional context or tool profiles are resolved through discoverable providers;
6. approval-gated operations can create durable suspension state;
7. runtime-specific configuration forms can read posted values through `IRequest`;
8. the AI service dashboard can inspect configured service definitions and invoke service testers.

External AI communication, conversation persistence, retrieval, tool behavior, and provider logging occur in the selected implementation components, not in the generic AssistantRuntime routers.

## 3. Agent execution routing

`RoutingAgentExecutionService` receives an `AgentExecutionRequest` and an optional event sink. The request can contain:

- agent configuration,
- user or application inputs,
- runtime context.

The service selects a runtime and passes the same request to that runtime's execution service.

AssistantRuntime does not sanitize or remove application-specific fields before this delegation. The caller and selected runtime are responsible for ensuring that the request contains only data required for the operation.

## 4. Conversation routing

`RoutingAgentConversationService` delegates conversation operations to the selected runtime. Supported operations include reading state, creating conversations, activating conversations, renaming, deleting, appending messages, and updating activity.

AssistantRuntime itself does not persist conversation titles, messages, or history. The selected runtime owns that storage behavior.

A privacy review must therefore inspect the actual runtime implementation for:

- conversation storage location,
- user or tenant binding,
- access checks,
- retention,
- deletion,
- export,
- logging,
- provider transfers.

## 5. Text-task routing

`RoutingAgentTextTaskService` delegates isolated text tasks to the selected runtime.

Text-task requests can contain task input, runtime configuration, and context as defined by the AssistantFoundation DTO. The routing component does not itself persist the task input or result.

## 6. Runtime selection and registry

The runtime registry discovers implementations through `IClassMap`. The selector reads the `agent_runtime` value from agent configuration and validates it against installed runtimes.

Runtime identifiers are technical identifiers. AssistantRuntime does not maintain a separate user profile or routing history for them.

The default selection behavior is intentionally strict. A missing explicitly configured runtime is reported as an error rather than being silently replaced by another runtime with potentially different tools, context, persistence, or provider behavior.

## 7. Context profiles

`AgentContextProfileService` discovers context-profile providers and delegates profile construction to the provider that owns the selected profile.

AssistantRuntime stores the discovered provider and profile objects in process memory for reuse. It does not persist context-profile definitions or generated context blocks.

A provider can build context from the current `AgentExecutionRequest`, so privacy-relevant processing depends on that provider implementation.

## 8. Tool profiles and tool execution

`AgentToolProfileService` resolves selected tool profiles to provider-owned tool sets. `CompositeAgentToolSet` combines capability catalogs and routes calls to the set that owns the requested tool.

Tool calls can contain:

- call identifiers,
- tool names,
- arbitrary argument arrays,
- execution metadata,
- tool results.

AssistantRuntime does not store normal tool-call arguments or tool results by default. Approval-gated calls can become part of suspension state as described below.

Concrete tool providers remain responsible for authorization, input validation, side-effect classification, external transfers, logging, and domain-specific data minimization.

## 9. Durable suspension storage

The default `StateStoreAgentSuspensionRepository` is the main persistent data-processing feature implemented directly by AssistantRuntime.

It stores state through the injected BASE3 `IStateStore`. AssistantRuntime does not select the physical State Store backend. Depending on project composition, the values can therefore be stored in a database or another backend supplied by the host application.

### 9.1 Active suspension state

For one pending suspension the repository stores:

- an internal format version,
- an opaque resume handle,
- creation timestamp,
- expiration timestamp,
- the serialized `AgentSuspension`.

The serialized suspension can include:

- suspension ID and scope ID,
- status,
- interaction requests,
- action information and summaries,
- server-owned execution state,
- metadata.

Because the `state` and `metadata` structures are generic, they can contain personal or confidential data if the runtime places such values into them.

### 9.2 State keys and scope identifiers

State keys use these prefixes:

```text
assistant.agent.suspension.state.
assistant.agent.suspension.claim.
assistant.agent.suspension.history.
```

The raw scope identifier is not used directly in the State Store key. The repository derives a fixed-length scope token from a SHA-256 hash of the scope identifier.

The serialized suspension itself can still contain its `scope_id`, so hashing the storage key is not equivalent to anonymizing the stored suspension payload.

### 9.3 Resume handles

Resume handles are generated from cryptographically secure random bytes combined with the scope token. They are opaque technical credentials for resuming one server-owned suspension.

Clients should treat resume handles as sensitive one-time authorization material. They should not be logged, exposed to unrelated users, or retained beyond the interaction lifecycle without a specific reason.

### 9.4 Claim leases

When a resume attempt begins, the repository creates a claim record containing:

- status `claimed`,
- a random claim token,
- claim timestamp.

The default claim lease TTL is 30 seconds. A recoverable attempt can release its claim.

### 9.5 Replay protection

When a suspension is consumed, the active suspension state is removed and the claim key is replaced by a `consumed` marker with a consumption timestamp.

The default replay-protection TTL is 86,400 seconds. This prevents the same resume handle from being processed again during that period.

### 9.6 Suspension history

The repository also maintains history for each scope. A history entry can contain:

- suspension ID,
- status and lifecycle,
- resume handle while represented in the stored history structure,
- creation and expiration timestamps,
- interaction request IDs,
- interaction kind,
- titles,
- messages,
- summaries,
- risk classification,
- resolution outcome,
- resolution source,
- resolution timestamp,
- response request IDs and explicit decisions.

For resolved response history, the implementation deliberately stores empty `input`, `note`, and `metadata` values instead of copying those fields from the interaction response.

### 9.7 History retention

Active suspension state uses the TTL supplied by the caller. Claim leases and consumed replay markers use explicit TTLs.

The history record itself is written through `IStateStore::set()` without a TTL in the current implementation. It can therefore persist until explicitly deleted, replaced, or removed by the selected State Store backend or an operational cleanup process.

A production installation should define a retention and deletion policy for `assistant.agent.suspension.history.*` keys when interaction text, summaries, decisions, or other privacy-relevant values can occur there.

## 10. Configuration forms and request data

`CompositeAgentConfigFormService` reads the selected runtime identifier from `IRequest` and delegates runtime-specific posted fields to the selected runtime form service.

The component itself normalizes and returns settings but does not persist them.

Runtime-specific form implementations can process credentials, provider settings, model names, tool selections, or other sensitive configuration. Those fields and their persistence must be reviewed in the corresponding runtime and host components.

## 11. AI service dashboard

`AiServiceDashboardDisplay` reads the application configuration and discovers `IAiServiceTester` implementations.

For supported configured services, it can process:

- service identifier and group,
- endpoint,
- recognized credential fields such as `apikey`, `token`, `access_token`, `key`, or `secret`,
- tester results.

### 11.1 Masked display values

The dashboard does not display the full credential value. It produces a shortened representation containing a small prefix and suffix with masked characters in between.

Endpoints are also shortened for the dashboard view.

Masked credentials remain security-relevant because they confirm that a credential exists and reveal small fragments. Access to the dashboard should therefore be limited to appropriate administrative users by the host application.

### 11.2 Service tests

When the dashboard receives `action=test`, it passes the complete configured service section to the matching `IAiServiceTester`.

The tester implementation determines whether this causes an external network request and which fields are transmitted. AssistantRuntime does not independently filter the service configuration before handing it to the tester.

For each active tester, the installation should document:

- target service,
- endpoint,
- transmitted credential and configuration fields,
- test payloads,
- remote logging and retention,
- who is authorized to trigger the test.

## 12. Execution events and diagnostics

`AgentEventDispatcher` forwards `AgentExecutionEvent` values to an optional run-scoped event sink.

Event payloads are generic arrays and can contain privacy-relevant data if a runtime emits such values. The dispatcher does not redact payloads.

`CollectingAgentEventSink` stores emitted events only in process memory. It is not a durable event log and introduces no persistence by itself.

Other event-sink implementations can stream or persist events and must be reviewed separately.

## 13. Logging

AssistantRuntime does not implement a built-in persistent prompt, response, tool-call, or event log.

Errors thrown by routing, profile aggregation, suspension handling, or service testing can still be captured by the host application's general logging and error handling. The host should avoid recording full agent payloads, credentials, resume handles, or suspension state in logs unless there is a defined operational need.

## 14. External provider transfers

The routing services do not call external AI services directly. External transfers occur only when the selected runtime, provider, tool, parser, retrieval backend, speech service, or service tester performs such a call.

AssistantRuntime can pass request data to those components unchanged. A complete privacy inventory therefore requires the concrete runtime and provider configuration in addition to this component-level documentation.

## 15. Data minimization

Applications using AssistantRuntime should in particular:

- keep agent execution context limited to required values,
- avoid placing secrets into generic context or event payloads,
- avoid persisting unnecessary personal data inside suspension state or metadata,
- keep interaction summaries concise,
- protect opaque resume handles,
- restrict access to the AI service dashboard,
- use runtime-specific configuration forms only for necessary settings,
- review tool and context profile providers for hidden data expansion.

## 16. Deletion and retention

AssistantRuntime has no global user-data deletion workflow.

A production installation should define deletion behavior for at least:

- active suspension state,
- suspension history,
- consumed replay markers,
- runtime-specific conversation data,
- runtime-specific memory,
- host-persisted agent configuration,
- logs produced by the host or selected runtime,
- remote provider logs created by service tests or runtime execution.

The physical deletion mechanism for suspension records depends on the configured `IStateStore` backend.

## 17. Production review checklist

Before production use, review and document:

- installed runtime implementations and their runtime IDs,
- active context-profile and tool-profile providers,
- the selected `IStateStore` backend,
- retention for `assistant.agent.suspension.history.*`,
- active-suspension TTL values supplied by runtimes,
- access control around resume operations,
- treatment of resume handles in clients and logs,
- runtime-specific conversation and memory persistence,
- event-sink implementations,
- configuration-form fields and persistence,
- access control for the AI service dashboard,
- active `IAiServiceTester` implementations and their external calls,
- host logging and error logging,
- external runtime and provider data flows.

## 18. Component boundary

This document describes AssistantRuntime only. Concrete agent runtimes, AI providers, retrieval systems, tool implementations, conversation stores, user interfaces, and host applications introduce additional processing and should maintain their own component-level privacy documentation.
