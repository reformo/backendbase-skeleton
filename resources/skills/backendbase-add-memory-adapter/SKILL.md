---
name: backendbase-add-memory-adapter
description: Add an in-memory Backendbase adapter that implements existing context read or write ports for deterministic tests. Use for fast handler and lifecycle evidence; do not use as production persistence, as a replacement for Doctrine tests, or to introduce new business behavior.
---

# Add a Backendbase Memory Adapter

## Outcome

Provide a small deterministic test adapter whose observable contract matches the production port closely enough for in-process use-case tests.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespaces, layers, container and test layout, and the nearest memory adapter.
3. Read every method on the implemented port and the production adapter's absence, removal, ordering, pagination, and error semantics.
4. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
5. Confirm which tests need the adapter and which production behaviors still require integration evidence.

## Target-project adaptation

Adapt storage keys, cloning, projection mapping, filters, ordering, pagination, and exceptions. Do not copy Example groups, fields, removal model, or insertion ordering.

## Workflow

1. Reuse the production port without adding test-only methods to it.
2. Add one small store when read and write adapters need shared in-process state.
3. Clone stored and loaded mutable aggregates to prevent reference aliasing.
4. Implement the write adapter with production-equivalent not-found and removal behavior.
5. Implement only read methods required by the selected tests, with production-equivalent filtering and deterministic ordering.
6. Bind adapters explicitly in tests, not in production `ServiceProvider` definitions.
7. Add or update lifecycle tests through real command and query buses.

## Backendbase invariants

- Implement the same context-owned ports as production adapters.
- Do not add business decisions or relax invariants.
- Preserve `null`, empty, not-found, removal, ordering, and pagination semantics.
- Keep tests deterministic and free of network or database calls.
- Do not claim SQL, Doctrine mapping, uniqueness, transaction, or lock behavior from memory tests.
- Do not create a production adapter, schema field, or feature only to support the test double.

## Verification

Run the context-owned service tests, affected handler tests, and complete in-process lifecycle test. Run PHPStan and PHPCS when code changed.

## Completion report

Report implemented ports, semantic parity decisions, intentional test-only limits, tests, commands run, and skipped checks.
