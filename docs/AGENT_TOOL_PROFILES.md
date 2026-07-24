# Agent tool profiles

## Boundaries

Tool profiles are resolved through the existing runtime-neutral contracts in
AssistantFoundation:

- `IAgentToolProfileProvider` owns one stored profile namespace;
- `IAgentToolProfileService` discovers providers and combines selected profiles;
- `IAgentToolSet` exposes the existing `AgentCapabilityCatalog` and executes one
  selected function through the provider-owned run-local context.

Tool metadata remains `AgentCapability`; execution results remain
`AgentToolResult`. No parallel tool-definition or tool-result DTO hierarchy is
introduced.

`IAgentConfirmableToolSet` is an optional extension used only by tool sets that
can pause an exact mutation for an explicit decision and resume that exact
reviewed call later. It reuses the existing `AgentSuspension`,
`AgentInteractionRequest`, `AgentInteractionResponse` and mutation snapshot
DTOs.

## Safety boundary

A shared profile provider may expose:

- functions explicitly classified as read-only;
- functions explicitly classified as mutations only when they also declare
  `requiresApproval=true`;
- guarded mutations only when their concrete tool implements the existing
  commit-guard contract.

Missing or ambiguous side-effect annotations remain unsafe and the function is
omitted with a warning. Duplicate profile IDs and duplicate effective tool
names are rejected; no last-one-wins behavior exists.

## Runtime ownership

AssistantRuntime combines provider-owned tool sets but does not know concrete
tool implementations. MissionBay provides the stored tool profiles, materializes
their configured component presets and owns contract validation, action review,
commit guards and concrete invocation. NeuronAi maps the resulting capabilities
to native Neuron tools.

Read-only calls execute immediately. A mutation call produces a server-owned
suspension. The opaque resume handle is stored by the shared
`IAgentSuspensionRepository`; only an explicit response for the exact interaction
request can continue the action.

## Resume lifecycle

```text
runtime tool call
  -> provider-owned input validation
  -> exact action and fingerprint
  -> optional commit snapshot
  -> AgentInteractionRequest
  -> durable opaque resume handle
  -> explicit approve or deny response
  -> binding and fingerprint validation
  -> final commit guard
  -> concrete tool invocation
  -> normalized AgentToolResult
```

After the structured response has been validated, the one-time handle is
consumed before any approved mutation executes. The reviewed call is restored
from server-owned suspension state and is never reconstructed from
client-supplied arguments. The client sends only the opaque handle plus the
structured response.

## Run-local execution metadata

`IAgentToolSet::execute()` accepts an optional metadata array. Runtime adapters
use it for call identity, display labels and loop counters. Provider-owned tool
sets merge these values with their run-local context before invoking the
concrete tool. This keeps audit concerns out of tool implementations and avoids
a second execution-context abstraction.
