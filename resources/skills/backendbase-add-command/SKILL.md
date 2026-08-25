---
name: backendbase-add-command
description: Add a synchronous Backendbase CQRS command and handler for one state-changing use case, with domain behavior and focused tests. Use for writes in an existing context; do not use for queries, HTTP-only changes, asynchronous commands, or unrequested integration events.
---

# Add a Backendbase Command

## Outcome

Create one serializable command and one resolvable handler that changes state through domain behavior and project-owned ports.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespaces, layer and container layout, test roots, and the nearest command and handler.
3. Trace the relevant aggregate, write port, transaction mechanism, caller boundary, domain-event publisher, and container registration path.
4. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
5. Confirm input fields, invariant, result semantics, transaction scope, and whether any event is explicitly required.

## Target-project adaptation

Use the target project's namespace, command naming, value objects, aggregate, ports, and test locations. Do not copy Example fields, serialization, table concepts, or event names.

## Workflow

1. Validate and convert untrusted input at its external boundary before command construction.
2. Add the command under the context command contracts with one positional handler attribute.
3. Carry only data needed by this use case and define a stable `toArray()` shape.
4. Add the handler under the project's current command-handler directory.
5. Load or create the aggregate, invoke domain behavior, and persist through a write port.
6. Register or autowire the handler and its dependencies through the target composition root.
7. Add an integration event only when a real cross-process fact is requested. Keep the business write and outbox insert in one transaction.
8. When a synchronous domain-listener failure must roll back the write, publish the domain event inside the same transaction callback after the required persistence operation.
9. Test command serialization, handler orchestration, real bus resolution, the changed lifecycle, event ordering, and transaction rollback when a transaction is used.

## Backendbase invariants

- Implement `Command`; return `void` from the command bus and handler.
- Use exactly one positional `#[CQRSHandler(HandlerClass::class)]` attribute.
- Do not use a named attribute argument; current buses read argument index `0`.
- Put business decisions on domain objects, not controllers, handlers, or adapters.
- Do not run SQL, build HTTP responses, or publish directly to a broker in the handler.
- Do not call the internal integration-event manager directly from the handler.
- The handler attribute does not register the handler. The container must resolve the handler and all dependencies.
- When an integration event is required, the persisted state and outbox row commit or roll back together.
- Do not create a fake integration event only to obtain an outbox transaction. Use the target's ordinary transaction mechanism when atomic database work is required without an outbound fact.
- Place synchronous domain-event publication inside the real transaction only when listener failure is part of command rollback semantics.
- A preceding controller query is not atomic with the command. Reload state and enforce current invariants in the handler.
- Do not invent a result, table field, event, adapter, or timestamp outside the request.

## Verification

Run the contract test, handler test, real command-bus composition test, affected lifecycle test, architecture tests, PHPStan level 8, and PHPCS. For an outbox branch, force an outbox failure and verify the business write rolls back. For a transactional domain event, assert persistence and publication order inside the callback.

## Completion report

Report the command contract, handler, domain operation, port and transaction use, event decision, tests, commands run, and skipped checks.
