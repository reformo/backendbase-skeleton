---
name: backendbase-add-memory-adapter
description: Add an in-memory Backendbase adapter that implements existing context read or write ports for deterministic tests. Use for fast handler and lifecycle evidence; do not use as production persistence, as a replacement for Doctrine tests, or to introduce new business behavior.
---

# Add a Backendbase Memory Adapter

## Outcome

Provide a small deterministic test adapter whose observable contract matches the production port closely enough for in-process use-case tests.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the affected ports, nearest memory adapter, shared adapter contract tests, and lifecycle tests.
3. Read every method on the implemented port and the production adapter's absence, removal, ordering, pagination, and error semantics.
4. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.
5. Confirm which tests need the adapter and which production behaviors still require integration evidence.

## Target-project adaptation

Adapt storage keys, cloning, projection mapping, filters, ordering, pagination, and exceptions. Do not copy Example groups, fields, removal model, or insertion ordering.

## Workflow

1. Reuse the production port without adding test-only methods to it.
2. Add one small store when read and write adapters need shared in-process state.
3. Clone stored and loaded mutable aggregates to prevent reference aliasing.
4. Implement the write adapter with production-equivalent not-found and removal behavior.
5. Implement only read methods required by the selected tests, with production-equivalent filtering and deterministic ordering.
6. Run the production adapter's behavioral repository contract suite against the memory adapter.
7. Bind adapters explicitly in tests, not in production `ServiceProvider` definitions.
8. Add or update lifecycle tests through real command and query buses.

## Backendbase invariants

- Implement the same context-owned ports as production adapters.
- Do not add business decisions or relax invariants.
- Preserve `null`, empty, not-found, removal, ordering, and pagination semantics.
- Keep tests deterministic and free of network or database calls.
- Match observable uniqueness behavior. Do not claim database constraint enforcement, SQL, Doctrine mapping, transaction, or lock behavior from memory tests.
- Do not create a production adapter, schema field, or feature only to support the test double.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Run the context-owned service tests, affected handler tests, and complete in-process lifecycle test. Run PHPStan, the configured complexity check, and PHPCS when code changed.

## Completion report

Report implemented ports, semantic parity decisions, intentional test-only limits, tests, commands run, and blocked required checks.
