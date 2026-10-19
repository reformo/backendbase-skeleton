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

The migration history contains four installation migrations. They create eleven application tables directly with their final names:

| Migration | Final schema |
| --- | --- |
| `Version20260823000000` | Outbox, inbox, delivery failures, replay and resolution evidence, destinations, final claim and failure column names, and cleanup indexes |
| `Version20260823215059` | Example binary UUID keys, virtual public UUIDs, versions, microsecond timestamps, active uniqueness, and query indexes |
| `Version20261014020000` | `account` with required email and mobile active keys, nullable first/last names, and required `password_hash VARCHAR(255)`; `user`, `admin`, and `contact_verification` tables |
| `Version20261018000000` | Durable HTTP replay claims, encrypted responses, and an expiry index |

The release manifest pins `Version20261018000000`. Application-only rollback remains unsafe because older applications require the former table and column names.
Messaging, identity, and HTTP replay installation rollbacks reject removal of their retained evidence.
Example rollback drops its table only through an explicitly authorized operation.
Fresh installation creates no Example shadow or legacy table and performs no copy or rename.
See [migration consolidation and adoption](operations/migration-consolidation.md).

The former recovery, Example conversion, query-index, and naming migrations have been incorporated into these installations.
Do not rerun consolidated installation migrations on an existing database.
Existing databases require a separate adoption plan based on their schema, data, and recorded versions.
Do not delete data or mark versions executed without verifying every consolidated object.

Basis: `resources/docs/9-persistence-and-database.html`, `resources/docs/12-deployment-and-operations.html`.

The CLI keeps migration metadata visible to migration status and execution.
ORM validation excludes that technical table. The diff command filters business tables after migration status checks.

## Final installation names

The identity installation creates `account`, `user`, `admin`, and `contact_verification` with their final index names.
The corresponding mapped classes are `Account`, `User`, and `Admin`. Use explicit aliases when domain classes have the same names.
Quote the `admin` identifier in SQL and ORM metadata because the MySQL keyword catalog includes it.
The Doctrine CLI uses `MappedTableFilter` for schema discovery and migration diffs. It accepts plain, MySQL-quoted, and DBAL-quoted names.

The messaging installation creates `claim_expires_at` directly in the outbox and inbox, including their indexes.
The outbox creates `last_failure_type` directly. It stores a failure type, not an error message.
The contact Doctrine entity is `ContactVerification`. Public contact response fields keep their released names.

Fresh installation performs no ALTER, rename, backfill, or data copy.
Column types, defaults, index column order, uniqueness, and identifiers match the former history's final schema.
A fresh database runs all four migrations in order. Existing databases require a separate adoption plan, including earlier consolidated installations.
The version identifier alone does not establish schema equivalence because the installation definitions have changed.
After partial failure, inspect completed tables and indexes before choosing recovery. MySQL table creation is non-transactional.
Offline SQL and SQLite tests do not prove native MySQL execution. Verify migration, uniqueness, and ORM parity on a prepared MySQL 8.4 target.

## Operator history

Installation migrations omit `operator_action` and its indexes. There is no operator table creation, rename, or drop migration.
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
