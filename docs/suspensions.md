# Durable Agent Suspensions

## Purpose

`StateStoreAgentSuspensionRepository` is the default AssistantRuntime implementation of `IAgentSuspensionRepository`.

It keeps approval/interaction suspensions durable across HTTP requests without exposing serialized execution state to the client.

## State keys

The repository uses two internal prefixes:

```text
assistant.agent.suspension.state.
assistant.agent.suspension.claim.
```

Additional scope and replay information is stored as part of the repository lifecycle.

## Lifecycle

1. `create()` stores a suspension with a TTL and returns an opaque resume handle.
2. `findPending()` can locate current pending state for a scope.
3. `claim()` leases one handle and returns an `AgentSuspensionClaim` containing a claim token.
4. `release()` releases a recoverable claim.
5. `consume()` marks successful completion and prevents replay.

## Claim lease

The default constructor uses a short claim TTL so concurrent requests cannot process the same suspension at the same time indefinitely.

## Replay protection

Consumed handles remain protected by a replay marker for a longer TTL. A client repeating an old approval response must not execute the mutation a second time.

## Storage boundary

`IStateStore` is the operational persistence dependency. The repository does not create an independent database or settings profile.
