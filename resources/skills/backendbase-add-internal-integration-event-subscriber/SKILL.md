---
name: backendbase-add-internal-integration-event-subscriber
description: Add a synchronous in-process subscriber for an existing Backendbase integration event, including explicit dispatch ownership and service-provider registration. Do not use for queue-delivered external events or domain-event listeners.
---

# Add an internal integration-event subscriber

Read [references/backendbase-pattern.md](references/backendbase-pattern.md) before editing.

## Workflow

1. Read applicable `AGENTS.md` files and discover the target namespace, event manager, subscriber contract, registration mechanism, and nearest subscriber test.
2. Confirm the required dispatch is synchronous and in-process.
3. Identify the existing non-command-handler boundary that will explicitly dispatch the integration event.
4. Add one subscriber with narrow dependencies and register only its event names and class.
5. Test exact and wildcard matching only when the requested behavior needs both.

## Invariants

- Internal subscribers receive producer `IntegrationEvent` objects, not queue carriers.
- Registration alone does not execute a subscriber.
- Do not call the internal event manager directly from a command handler.
- Keep internal and external subscriber interfaces separate.
- Do not add a subscriber when no valid dispatch boundary exists.

## Completion report

Report the event, dispatch owner, subscriber, registration, execution order, tests, and any missing invocation path.
