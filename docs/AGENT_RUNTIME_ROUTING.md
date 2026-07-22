# Agent runtime routing

## Boundaries

- `AssistantFoundation`: contracts, DTOs, models and exceptions only.
- `AssistantRuntime`: shared concrete registry, selector, router, form composer
  and event sinks.
- Runtime plugins: one `IAgentRuntimeService` and one
  `IAgentRuntimeConfigFormService` with the same runtime ID.
- Host plugin: migration-safe default selection and host credentials.

## Background agents

Agent Admin stores `agent_runtime` directly. Manual execution and scheduled jobs
both call the same `IAgentExecutionService`, so no worker-specific runtime logic
exists.

## Chatbot

The Chatbot configuration exposes one backend field:

- `service:<id>` for a direct service such as Dummy;
- `runtime:<id>` for an installed agent runtime.

Runtime configuration sections are rendered without a second visible selector.
The selected backend activates exactly one section and disables every inactive
section before HTML validation and submission.

Legacy `service` settings are converted to `chatbot_backend` on the next save.

## Form composition

`CompositeAgentConfigFormService` can render its own runtime selector for Agent
Admin or be controlled by an external selector for Chatbot. It accepts an
explicit runtime ID for request parsing, so server-side validation does not rely
on JavaScript-updated hidden fields.
