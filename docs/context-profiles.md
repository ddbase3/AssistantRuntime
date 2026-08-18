# Context Profiles

## Purpose

AssistantRuntime aggregates runtime-neutral context-profile providers.

## Provider discovery

`AgentContextProfileService` discovers `IAgentContextProfileProvider` instances through `IClassMap`.

Each provider exposes a stable provider id and profile options. The aggregate service presents one profile option list to consumers.

## Global profile namespace

A profile id must resolve unambiguously. Providers should therefore use stable ids and avoid accidental overlap.

The service does not copy or persist profile definitions. Ownership stays with the provider.

## Build flow

```text
profile id
  -> find owning provider
  -> provider.build(profile id, AgentExecutionRequest)
  -> AgentContextProfileResult
  -> ordered AgentInstructionBlock values + warnings
```

## Responsibility boundary

The aggregate service does not decide how a runtime converts instruction blocks into provider-specific system messages. That mapping belongs to the selected runtime.
