---
name: backendbase-add-query
description: Add a Backendbase CQRS query, declared result, optional read model, read-port method, and query handler for one read use case. Use for reads in an existing context; do not use for state changes, DBAL adapter implementation alone, or HTTP response shaping alone.
---

# Add a Backendbase Query

## Outcome

Create one typed read contract and one resolvable handler that returns a declared projection without loading or changing an aggregate.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the nearest query flow, result contract, read port, handler registration, and focused tests.
3. Trace the caller, existing read port, result models, adapter, ordering, pagination, and not-found semantics.
4. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.
5. Confirm criteria, result type, nullable or empty meaning, and whether a new projection is required.

## Target-project adaptation

Preserve the target project's namespace, query naming, value objects, read-model style, and test ownership. Do not copy Example criteria, response fields, table concepts, pagination, or null behavior.

## Workflow

1. Validate and convert untrusted input before query construction.
2. Identify the selected resolver class. For Backendbase registry mode, add a query contract with `@implements Query<TResult>` and its context `ServiceProvider::getHandlers()` mapping.
3. Reuse an existing scalar, list, page, or read model when it exactly matches the use case. Otherwise add the smallest immutable result model.
4. Add or extend a context-owned read port for the requested capability.
5. Add the query handler with matching `QueryHandler<TQuery, TResult>` PHPDoc.
6. Carry typed access control when the query needs a named privilege. Check it in the handler before the read.
7. Delegate the read to the port. Keep SQL and row mapping in the adapter.
8. Test serialization, handler-to-port delegation, result semantics, and the affected lifecycle.

## Backendbase invariants

- In an unmodified Backendbase project, keep the query free of handler imports and attributes.
- Map the query to its handler in the context `ServiceProvider::getHandlers()`.
- Declare the query result with PHPDoc generics on both query and handler.
- Return a read model, page, scalar, list, or `null`; never return a Doctrine record or raw row.
- Query handlers must not load or mutate aggregates, control write transactions, or create events.
- A privileged query handler checks typed access control before repository or external work.
- Keep SQL in a DBAL read adapter.
- Treat `QueryBus::handle()` generics as static-analysis metadata; runtime return type is `mixed`.
- Do not add persistence, schema, events, or response fields outside the request.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Run the query contract test, affected read-port or adapter tests, lifecycle tests, PHPStan level 8, the configured complexity check, and PHPCS. Run architecture tests when dependencies changed.

## Completion report

Report the criteria, declared result and empty semantics, read model and port changes, commands run, and blocked required checks.
