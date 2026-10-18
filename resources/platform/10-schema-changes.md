# Schema Changes

Database structure requires explicit feature scope. Do not add tables, columns, indexes, timestamps, soft deletion, status, or audit data without authorization.

For new or changed JSON columns, identify the declared root shape and apply the [JSON column policy](09-persistence.md#json-column-policy).
Record the typed class or explicit exception before finalizing mapping. A PHP representation change alone does not require a migration.
Review stored-data compatibility before changing required fields or accepted JSON shapes.

## Migration flow

1. Complete domain and repository behavior.
2. Make repository tests pass.
3. Run `bin/doctrine migrations:diff` from the project root.
4. Review every generated SQL statement.
5. Remove unrelated generated changes.
6. Use plain `CREATE TABLE` so an unexpected existing table stops the migration. Use `IF NOT EXISTS` only with an explicit adoption plan and exact schema validation.
7. Run `bin/doctrine migrations:migrate --dry-run --no-interaction` against an identified, prepared target.
8. Apply with `bin/doctrine migrations:migrate --no-interaction` only with explicit authority for that target database.
9. Verify the resulting schema and repository behavior.

Do not run a migration that contains an unrequested change. Report the difference and request a decision.

Do not apply a migration to an unspecified, shared, stage, or production database without explicit authority for that database.

Release seeders contain only approved reference data. Make seeders idempotent. Do not add environment-specific or test data to production seeders.
The explicitly approved initial Admin uses a separate manual bootstrap. It never runs from a migration or release.
See [Admin bootstrap](operations/identity-operator.md#approved-initial-admin-seeder).

Review migration rollback and production compatibility before release.

Required indexes for authorized query patterns must be included in the same change as the query.
Review existing primary, unique, and composite index prefixes before adding an index.
Keep ORM index metadata and forward migrations aligned. Do not rewrite applied migration history to add an index.
For non-transactional index creation, inspect completed indexes after partial failure before planning recovery.

Migration-history rewrites require a separate adoption plan for databases that applied the old versions. Compare schema and version records before any migration command.

## Current migration history

The migration history contains three consolidated installation migrations, two forward naming migrations, and one HTTP replay migration:

| Migration | Final schema |
| --- | --- |
| `Version20260823000000` | Outbox, inbox, delivery failures, replay and resolution evidence, destinations, and cleanup indexes |
| `Version20260823215059` | Example binary UUID keys, virtual public UUIDs, versions, microsecond timestamps, active uniqueness, and query indexes |
| `Version20261014020000` | Account with required email and mobile active keys, nullable first/last names, and required `password_hash VARCHAR(255)`; User, Admin, and contact verification tables |
| `Version20261017000000` | Remove `identity_` from Account, User, and Admin table and index names; preserve rows, columns, and index definitions |
| `Version20261017010000` | Rename contact verification, claim expiry, and failure type without changing stored values |
| `Version20261018000000` | Add durable HTTP replay claims, encrypted responses, and an expiry index |

The release manifest pins `Version20261018000000`. Application-only rollback remains unsafe because older applications require the former table and column names.
Messaging and identity installation rollbacks reject removal of their retained evidence.
Example rollback drops its table only through an explicitly authorized operation.
Fresh installation creates no Example shadow or legacy table and performs no copy or rename.
See [migration consolidation and adoption](operations/migration-consolidation.md).

The former recovery, Example conversion, and query-index migrations have been incorporated into these installations.
Do not rerun consolidated installation migrations on an existing database.
The naming migration supports an existing database at the exact `Version20261014020000` consolidated schema after target review.
Existing databases require a separate adoption plan based on their schema, data, and recorded versions.
Do not delete data or mark versions executed without verifying every consolidated object.

Basis: `resources/docs/9-persistence-and-database.html`, `resources/docs/12-deployment-and-operations.html`.

The CLI keeps migration metadata visible to migration status and execution.
ORM validation excludes that technical table. The diff command filters business tables after migration status checks.

## Remove bounded-context table prefixes

`Version20261017000000` renames `identity_account` to `account`, `identity_user` to `user`, and `identity_admin` to `admin`.
It removes the same prefix from four index names. Index columns, column order, and uniqueness stay unchanged.
`contact_verification`, `example_table`, and infrastructure messaging and recovery tables stay unchanged.
The corresponding mapped classes are `Account`, `User`, and `Admin`. Use explicit aliases when domain classes have the same names.
Quote the `admin` identifier in SQL and ORM metadata because the MySQL keyword catalog includes it.
The Doctrine CLI uses `MappedTableFilter` for schema discovery and migration diffs. It accepts plain, MySQL-quoted, and DBAL-quoted names.

Each table and its indexes change in one ALTER statement. Missing source names or existing target names make that statement fail.
The three statements are not one transaction. Review target names, existing indexes, schema parity, backup, and lock impact before execution.
Stop application traffic and workers that access these tables during migration and application activation. Mixed application versions are incompatible.
After partial failure, inspect each table and index name. Complete only the unapplied statements under the recovery plan.
Do not rerun the entire migration after partial success without reconciling the actual state.
The reverse migration preserves rows but requires the matching older application and separate target authorization.
Keep `application-rollback-safe` false. An application-only rollback cannot use the renamed tables.
Clear Doctrine metadata and proxy caches during deployment.

Offline SQL and SQLite tests do not prove MySQL ALTER execution. Verify native migration, uniqueness, and ORM parity on a prepared MySQL 8.4 target.

## Remaining naming migration

`Version20261017010000` follows the prefix-removal migration. It renames `contact_info` to `contact_verification` and its context-slot index.
It renames the outbox and inbox claim expiry columns to `claim_expires_at`.
The outbox `last_error` column becomes `last_failure_type`. It continues to store a failure type, not an error message.
The contact Doctrine entity is `ContactVerification`. Public contact response fields keep their released names.
All existing values, column types, defaults, index column order, uniqueness, and identifiers stay unchanged.

The three MySQL ALTER statements are non-transactional. Review all source and target names before execution.
After partial execution, inspect each affected object before choosing forward repair or the reviewed reverse migration.
The previous application cannot use the renamed objects. Keep application-only rollback disabled.
A fresh database runs all six migrations in order. Existing databases must satisfy the earlier adoption and recovery gates.

## Operator history

Installation and naming migrations omit `operator_action` and its indexes. There is no operator table creation, rename, or drop migration.
Admin state retains invitation and approval evidence. Operator setup and manual seeding use the same database-scoped MySQL advisory lock.

This migration-history rewrite does not change an existing database.
Existing installations require a separate adoption plan that compares actual tables, retained records, and executed migration versions.
Include any operator tables or removed migration versions from earlier history in that review.
Preserve recoverable evidence before any separately authorized table cleanup or migration-record reconciliation.
Do not rerun installation migrations, delete migration records, or infer schema equivalence from a recorded version alone.

## HTTP request replay

The HTTP replay migration creates `http_request_replay` without changing business tables.
Its reverse operation preserves evidence by refusing to drop the table.
See [HTTP idempotency](operations/http-idempotency.md) for deployment, expiry, indexes, and uncertain-outcome recovery.
