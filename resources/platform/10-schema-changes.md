# Schema Changes

Database structure requires explicit feature scope. Do not add tables, columns, indexes, timestamps, soft deletion, status, or audit data without authorization.

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

Seed only approved reference data. Make seeders idempotent. Do not add environment-specific or test data to production seeders.

Review migration rollback and production compatibility before release.

Migration-history rewrites require a separate adoption plan for databases that applied the old versions. Compare schema and version records before any migration command.

Basis: `resources/docs/9-persistence-and-database.html`, `resources/docs/12-deployment-and-operations.html`.
