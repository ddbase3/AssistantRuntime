# AssistantRuntime

AssistantRuntime contains the shared concrete runtime composition that must not
live in AssistantFoundation.

It owns:

- discovery and validation of installed agent runtimes;
- per-record runtime selection;
- routing through the generic `IAgentExecutionService`;
- routing explicit conversation operations through `IAgentConversationService`;
- routing isolated model-only tasks through `IAgentTextTaskService`;
- composition of runtime-specific configuration forms;
- collecting and dispatching agent event sinks;
- shared runtime administration displays.

AssistantFoundation remains limited to interfaces, DTOs, models and exceptions.
MissionBay and NeuronAi provide named runtime implementations and their own
runtime-specific forms.

Chatbot and Agent Admin use the same registry and router. The Chatbot presents a
single backend selector that combines direct services such as Dummy with all
registered agent runtimes. Agent Admin presents only the agent runtime selector.

## Context profiles

`AgentContextProfileService` discovers runtime-neutral context-profile providers
through the BASE3 class map. Profile IDs form one global namespace. The service
returns ordered instruction blocks for one `AgentExecutionRequest`; it does not
own profile storage or runtime-specific prompt mapping.

## Conversation and text-task routing

`RoutingAgentConversationService` and `RoutingAgentTextTaskService` use the same
strict runtime selector as normal execution. A runtime must explicitly implement
the matching runtime capability. Missing capabilities fail at that boundary; no
other runtime or profile is selected as a fallback.

Text tasks are intentionally separate from agent execution. They do not own a
conversation, do not materialize conversation memory, and receive no executable
tools.
