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
