---
name: backendbase-add-domain-event
description: Add a synchronous Backendbase domain event and one in-process listener for behavior inside the same service operation. Use when listener failure may participate in the current command flow; do not use for queue delivery, cross-service contracts, integration events, or general logging hooks.
---

# Add a Backendbase Domain Event

## Outcome

Publish one typed in-process fact to one resolvable listener at an explicit point in the owning use case.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the nearest domain event, publication owner, listener registration, transaction boundary, and affected tests.
3. Trace the command transaction, publisher binding, listener autowiring, composition root, and failure semantics.
4. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.
5. Confirm that synchronous same-service handling is required. Use an integration-event workflow for durable or cross-service delivery.

## Target-project adaptation

Adapt the event fact, payload fields, listener behavior, publish point, and transaction semantics. Do not copy Example event names, command payloads, logging, or timestamps as business requirements.

## Workflow

1. Define the business fact and the minimum explicit payload.
2. Add the event contract with one listener attribute and initialized occurrence time.
3. Add one listener that validates the concrete event before handling it.
4. Publish at the explicit application orchestration point. When listener failure must roll back the command, publish after required persistence and before the same real transaction callback returns.
5. Keep network, broker, process, and filesystem work outside transactional domain listeners.
6. Add a composition test that resolves the attributed listener through the production publisher and target container.
7. Test payload serialization, listener type rejection, publication count, ordering, and rollback placement when relevant.

## Backendbase invariants

- Implement `DomainEvent` and use `DomainEventTrait`.
- Call `initializeOccurredOn()` during construction.
- Use one positional `DomainEventListener` attribute; the publisher uses the first attribute.
- Implement the listener interface and reject an unexpected concrete event.
- Do not add domain-event metadata to a context `ServiceProvider`; attribute routing and container autowiring own this path.
- Do not call `recordEvent()` unless the target project has an explicit drain and publish mechanism.
- Do not create a fake integration event to obtain a transaction. Use an ordinary transaction abstraction unless an integration event is separately required.
- Do not route this event through the internal integration-event manager or a broker.
- Do not use a domain event for asynchronous or cross-context delivery.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Run focused event, listener, and affected command tests. Verify composition when registration changes, lifecycle behavior when state transitions change, and architecture when dependencies change. Run PHPStan level 8, the configured complexity check, and PHPCS.

## Completion report

Report the fact, payload, publish point, transaction effect, listener behavior, commands run, and blocked required checks.
