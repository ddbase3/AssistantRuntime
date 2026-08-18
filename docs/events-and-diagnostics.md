# Events and Diagnostics

## Purpose

AssistantRuntime contains small utilities for collecting and forwarding typed agent execution events.

## `AgentEventDispatcher`

`AgentEventDispatcher` reads the optional event sink from the current agent context and emits `AgentExecutionEvent` values without forcing every runtime helper to repeat sink lookup code.

## `CollectingAgentEventSink`

The collecting sink stores emitted events in memory and reports cancellation state.

It is useful for:

* tests;
* synchronous diagnostics;
* adapters that need the complete event sequence after execution.

It is not a durable event log.

## Cancellation

The event sink contract exposes `isCancelled()`. Long-running runtime operations may use this signal to stop work at appropriate safe boundaries.

## AI service dashboard

`AiServiceDashboardDisplay` is a shared display that inspects discoverable service-related components through framework services. Runtime/provider-specific configuration remains in the implementation plugins.
