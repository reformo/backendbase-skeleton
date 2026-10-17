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

The migration history supports fresh installations only. Three migrations create the final schema directly:

| Migration | Final schema |
| --- | --- |
| `Version20260823000000` | Outbox, inbox, delivery failures, replay and resolution evidence, destinations, and cleanup indexes |
| `Version20260823215059` | Example binary UUID keys, virtual public UUIDs, versions, microsecond timestamps, active uniqueness, and query indexes |
| `Version20261014020000` | Account with required email and mobile active keys, nullable first/last names, and required `password_hash VARCHAR(255)`; User, Admin, contact verification, and operator evidence tables |

The release manifest pins `Version20261014020000`. Application rollback to the previous integer-key version remains unsafe.
Messaging and identity rollbacks reject evidence removal. Example rollback drops its table only through an explicitly authorized operation.
Fresh installation creates no Example shadow or legacy table and performs no copy or rename.
See [migration consolidation and adoption](operations/migration-consolidation.md).

The former recovery, Example conversion, identity operator, and query-index migrations have been incorporated into these installations.
Do not run the consolidated history on a database that applied earlier versions.
Existing databases require a separate adoption plan based on their schema, data, and recorded versions.
Do not delete data or mark versions executed without verifying every consolidated object.

Basis: `resources/docs/9-persistence-and-database.html`, `resources/docs/12-deployment-and-operations.html`.

The CLI keeps migration metadata visible to migration status and execution.
ORM validation excludes that technical table. The diff command filters business tables after migration status checks.
