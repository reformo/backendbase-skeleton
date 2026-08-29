---
name: backendbase-add-seeder
description: Add an idempotent Backendbase database seeder for explicitly approved reference or lookup data, with repeat-run evidence. Use for release-owned stable data; do not use for test fixtures, environment-specific data, user data, speculative defaults, or schema creation.
---

# Add a Backendbase Seeder

## Outcome

Insert only approved stable reference data exactly once across repeated executions and releases.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespace, database layer, migration and test layout, and the nearest seeder.
3. Read the target schema, unique keys, existing data contract, and release migration flow.
4. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
5. Confirm the exact approved rows, stable identity, and whether a migration must invoke the seeder.

## Target-project adaptation

Adapt the seeder namespace, table, bound values, identity generation, natural-key existence check, and migration call. Do not copy Example row values, UUID choice, timestamps, table names, or environment data.

## Workflow

1. Define the stable natural key or other safe existence condition for each approved row.
2. Add a seeder in the discovered autoloaded seeder directory.
3. Use parameterized existence checks and return when data already exists.
4. Insert only the approved fields using the target project's date and identifier policy.
5. If requested, invoke the seeder from the owning migration's `postUp()` method when `up()` queues schema SQL with `addSql()`; Doctrine executes that SQL after `up()` returns.
6. Add a database test that runs the seeder twice and proves one correct result.
7. Verify migration behavior separately when the seeder is release-invoked.

## Backendbase invariants

- Keep seeders under `resources/database/Seeders` with namespace `Backendbase\Seeders` in an unmodified project.
- Make every seeder idempotent.
- Bind existence-check parameters; do not interpolate input.
- Seed only explicitly approved reference or lookup data.
- Do not add test, user, tenant, secret, host, or environment-specific data.
- Do not invoke the reference Example seeder unless that exact data is requested.
- Do not create or alter schema as part of this skill.

## Verification

Run the focused seeder test and owning Doctrine repository directory. If a migration invokes the seeder, dry-run and verify that migration against a prepared database with explicit authority.

## Completion report

Report approved rows, idempotency key, migration invocation decision, tests, commands run, and skipped checks.
