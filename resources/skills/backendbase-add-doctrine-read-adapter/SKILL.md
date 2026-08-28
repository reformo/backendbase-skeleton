---
name: backendbase-add-doctrine-read-adapter
description: Implement or extend a Backendbase Doctrine DBAL read adapter and persisted-row mapper for an existing query port. Use for SQL projections, filtering, ordering, pagination, and read-model mapping; do not use for aggregate writes, query-contract design alone, or schema creation.
---

# Add a Backendbase Doctrine Read Adapter

## Outcome

Return a declared context read result through parameterized DBAL SQL and strict persisted-data mapping.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespaces, layers, container and test layout, Doctrine configuration, and the nearest DBAL read adapter.
3. Read the query, result type, read port, table mapping, removal rules, ordering contract, and pagination primitive.
4. Inspect how persisted rows are validated before read-model construction.
5. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
6. Confirm that all required columns and indexes already exist or that separate schema work is authorized.

## Target-project adaptation

Adapt SQL, selected fields, criteria, null semantics, filters, ordering, pagination, mapper validation, and read models. Do not copy Example table names, columns, limits, soft-delete filters, or response fields.

## Workflow

1. Implement the existing read-port method with use-case-specific SQL.
2. Select only required columns and preserve the target schema identifiers.
3. Bind every value. Bind page size and offset with integer parameter types.
4. Choose the DBAL fetch method from expected cardinality: scalar, one row, one column, or many rows.
5. Apply required visibility and removal filters explicitly.
6. Use deterministic ordering before pagination.
7. Return the declared empty, nullable, scalar, list, page, or read-model result.
8. Validate every persisted scalar, enum, date, boolean, and JSON shape before model construction.
9. Bind the read port to the adapter in the target composition root and prove runtime resolution.
10. Run shared read behavior against every adapter for the port. Keep malformed persisted data and SQL details in Doctrine-specific tests.

## Backendbase invariants

- Use Doctrine DBAL for read projections; do not reconstitute aggregates on the query path.
- Keep SQL in the read adapter and business decisions outside it.
- Use one executable statement per DBAL call. Do not use `SELECT *`.
- Never interpolate untrusted values or client field names into SQL.
- Allowlist any selectable or sortable column names.
- Do not require `executeQuery()` when a DBAL cardinality-specific fetch method expresses the operation directly.
- Do not reformat unrelated SQL or rename existing identifiers only to impose a style.
- Never return raw rows or Doctrine records across the port.
- A read adapter is incomplete until the production composition root resolves its port binding.
- Filter removed records according to the declared contract.
- Do not add columns, indexes, limits, or response fields without requested scope.

## Verification

Run focused mapper and repository tests, the provider or container composition test, affected query or lifecycle tests, PHPStan level 8, and PHPCS. Run schema validation only against a prepared database.

## Completion report

Report SQL criteria, bindings, ordering, result and empty semantics, mapper validation, tests, commands run, and skipped checks.
