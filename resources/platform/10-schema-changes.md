# Schema Changes

Database structure requires explicit feature scope. Do not add tables, columns, indexes, timestamps, soft deletion, status, or audit data without authorization.

## Migration flow

1. Complete domain and repository behavior.
2. Make repository tests pass.
3. Run `bin/doctrine migrations:diff` from the project root.
4. Review every generated SQL statement.
5. Remove unrelated generated changes.
6. Use `CREATE TABLE IF NOT EXISTS` for new tables.
7. Run `bin/doctrine migrations:migrate --no-interaction`.
8. Verify the resulting schema and repository behavior.

Do not run a migration that contains an unrequested change. Report the difference and request a decision.

Seed only approved reference data. Make seeders idempotent. Do not add environment-specific or test data to production seeders.

Review migration rollback and production compatibility before release.

Basis: `resources/docs/9-persistence-and-database.html`, `resources/docs/12-deployment-and-operations.html`.
