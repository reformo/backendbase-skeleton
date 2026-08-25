---
name: backendbase-add-doctrine-write-adapter
description: Add or change a Backendbase Doctrine ORM write port implementation, mapped record, aggregate translation, provider binding, and repository tests. Use when an existing aggregate needs persistence; do not use for read projections, schema-only work, or unrequested database design.
---

# Add a Backendbase Doctrine Write Adapter

## Outcome

Persist and reconstitute an aggregate through a context-owned port while keeping Doctrine and storage decisions outside the domain.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespace, layer and container layout, test roots, Doctrine configuration, and the nearest write adapter.
3. Read the aggregate creation, reconstitution, identity, snapshot, removal, and failure behavior.
4. Inspect entity discovery, custom Doctrine types, provider bindings, and repository-test setup.
5. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
6. Confirm the requested persistence behavior and authorized schema scope before mapping fields.

## Target-project adaptation

Adapt the port operations, identifier, table and column mapping, enum and JSON handling, timestamps, removal semantics, and exception type. Do not copy Example table names, indexes, generated columns, uniqueness, or lifecycle fields.

## Workflow

1. Define or refine the smallest context-owned write port needed by handlers.
2. Add a mapped record in a discovered Doctrine entity directory.
3. Translate new aggregate state with `fromDomain()`, existing state with `synchronize()`, and persisted state with `toDomain()` or the target equivalents.
4. Implement load and save operations through `EntityManagerInterface` without business decisions.
5. Bind the port to the adapter in the context provider.
6. Add repository tests using production mapping metadata and `SchemaTool`.
7. Finalize mapping and repository behavior before generating a migration diff.
8. Start migration work only when the user authorized the exact schema change.

## Backendbase invariants

- Domain and contracts must not import Doctrine.
- Handlers depend on the write port, not the Doctrine adapter.
- Record and adapter map state; aggregate methods decide behavior.
- Active lookups must enforce the domain's removal semantics consistently.
- In an unmodified Backendbase project, map native string-backed enums with `Types::ENUM` and `enumType: EnumClass::class`.
- Adapt enum storage to the target database and Doctrine version when they use a different supported mapping.
- Keep mapped records in a directory discovered by both runtime and Doctrine CLI.
- Do not add a table, column, index, timestamp, status, or soft deletion without explicit scope.

## Verification

Run focused mapping and repository tests, the provider test, architecture tests, PHPStan level 8, and PHPCS. Run `orm:validate-schema` only against a prepared database. Memory tests do not prove ORM mapping. Use the migration skill only after production-metadata repository tests pass.

## Completion report

Report the port, mapping decisions, aggregate translation, binding, schema impact, tests, commands run, and skipped checks.
