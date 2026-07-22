# AssistantRuntime

AssistantRuntime contains the shared concrete runtime composition that must not
live in AssistantFoundation.

It owns:

- discovery and validation of installed agent runtimes;
- per-record runtime selection;
- routing through the generic `IAgentExecutionService`;
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
