---
name: backendbase-add-internal-integration-event-subscriber
description: Add a synchronous in-process subscriber for an existing Backendbase integration event, including explicit dispatch ownership and service-provider registration. Do not use for queue-delivered external events or domain-event listeners.
---

# Add an internal integration-event subscriber

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and discover the target namespace, event manager, subscriber contract, registration mechanism, and nearest subscriber test.
2. Confirm the required dispatch is synchronous and in-process.
3. Identify the transaction boundary that dispatches the integration event. In unmodified Backendbase, the transaction wrapper dispatches its returned event before commit.
4. Add one subscriber with narrow dependencies and register only its event names and class.
5. Test exact and wildcard matching only when the requested behavior needs both.

## Invariants

- Internal subscribers receive producer `IntegrationEvent` objects, not queue carriers.
- Registration alone does not execute a subscriber.
- In unmodified Backendbase, both delivery flag values run local subscribers. Their database writes share the producer transaction, and failures roll back its work.
- Do not call the internal event manager directly from a command handler.
- Keep internal and external subscriber interfaces separate.
- Do not add a subscriber when no valid dispatch boundary exists.

## Completion report

Report the event, dispatch owner, subscriber, registration, execution order, tests, and any missing invocation path.
