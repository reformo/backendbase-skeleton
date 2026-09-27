---
name: backendbase-add-command
description: Add a synchronous Backendbase CQRS command and handler for one state-changing use case, with domain behavior and focused tests. Use for writes in an existing context; do not use for queries, HTTP-only changes, asynchronous commands, or unrequested integration events.
---

# Add a Backendbase Command

## Outcome

Create one serializable command and one resolvable handler that changes state through domain behavior and project-owned ports.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the nearest command and handler, affected domain behavior, transaction owner, registration, and focused tests.
3. Trace the relevant aggregate, write port, transaction mechanism, caller boundary, domain-event publisher, and container registration path.
4. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.
5. Confirm input fields, invariant, result semantics, transaction scope, and whether any event is explicitly required. For client-visible write results, select an approach below.

## Target-project adaptation

Use the target project's namespace, command naming, value objects, aggregate, ports, and test locations. Do not copy Example fields, serialization, table concepts, or event names.

## Workflow

1. Validate and convert untrusted input at its external boundary before command construction.
2. Identify the selected resolver class. For Backendbase registry mode, add the command contract and its context `ServiceProvider::getHandlers()` mapping.
3. Carry only data needed by this use case and define a stable `toArray()` shape.
4. Add the handler under the project's current command-handler directory.
5. Load or create the aggregate, invoke domain behavior, and persist through a write port.
6. Register or autowire the handler and its dependencies through the target composition root.
7. Add an integration event only when the use case requires it. Keep business writes, local subscriber writes, and any outbox insert in one transaction.
8. When a synchronous domain-listener failure must roll back the write, publish the domain event inside the same transaction callback after the required persistence operation.
9. Test command serialization, handler orchestration, real bus resolution, the changed lifecycle, event ordering, and transaction rollback when a transaction is used.

## Client-visible write results

Discover the target's response contract, identifier ownership, read consistency, and application-service conventions. In an unmodified Backendbase project, both approaches preserve `void` command handlers and buses:

- For an identifier-only response, generate the public identifier before a create command or reuse an existing resource's public identity. Pass it in the command and return it only after successful execution.
- For a resource representation, permit one application orchestrator in the owning context. It dispatches the command, waits for successful completion and commit, then uses a query or read port. It returns a declared read model or immutable result object to the caller.
- Keep input and result contracts in the target's contract layer. Keep HTTP mapping at the delivery boundary. Preserve context isolation, write authorization, and read authorization, including direct read-port calls.
- Keep authoritative write lookup and transaction control in the handler or its called application service. Do not read first to select the write target. Do not mutate the command or use events as a response channel.
- Verify that the read source satisfies the required consistency. A delayed projection cannot guarantee an immediate representation. Define missing-result and read-failure behavior after commit. Do not repeat the command to recover a response.
- Test identifier equality and command-failure behavior. For orchestration, test commit-before-read order, visibility, read authorization, read failures, and actual container resolution.

The second approach returns resource state at read time. It cannot recover a handler-only value that was not persisted. Resolve incompatible response requirements before implementation. Read [the result examples](references/backendbase-pattern.md#client-visible-write-results) when selecting the approach.

## Backendbase invariants

- Implement `Command`; return `void` from the command bus and handler.
- In an unmodified Backendbase project, keep the command free of handler imports and attributes.
- Map the command to its handler in the context `ServiceProvider::getHandlers()`.
- Put business decisions on domain objects, not controllers, handlers, or adapters.
- Do not run SQL, build HTTP responses, or publish directly to a broker in the handler.
- Do not call the internal integration-event manager directly from the handler.
- A handler mapping does not register the handler service. The container must resolve the handler and all dependencies.
- When an integration event is required, business writes, local subscriber writes, and any outbox row commit or roll back together.
- Do not create a fake integration event only to obtain a transaction. Use the target's ordinary transaction mechanism when no integration event is required.
- Place synchronous domain-event publication inside the real transaction only when listener failure is part of command rollback semantics.
- Keep authoritative write lookup and missing-state decisions in the handler. A controller must not use a query to translate public identity before dispatch.
- Do not invent a result, table field, event, adapter, or timestamp outside the request.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Run focused command and handler tests. Verify real bus composition when registration changes. Add lifecycle and architecture checks for affected behavior and dependencies. Run PHPStan level 8, the configured complexity check, and PHPCS. For an outbox branch, force an outbox failure and verify the business write rolls back. For a transactional domain event, assert persistence and publication order inside the callback.

## Completion report

Report the command contract, handler, domain operation, port and transaction use, event decision, tests, commands run, and blocked required checks.
