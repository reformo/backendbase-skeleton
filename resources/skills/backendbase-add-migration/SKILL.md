---
name: backendbase-add-migration
description: Generate, review, and verify a Backendbase Doctrine migration for an explicitly approved schema change. Use after domain mapping and repository behavior are final; do not use to design speculative tables, apply unrelated diffs, seed data, or mutate an unspecified database.
---

# Add a Backendbase Migration

## Outcome

Produce one reviewed migration whose SQL matches the authorized schema change and has an explicit forward and recovery plan.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespace, layer and container layout, test roots, migration configuration, Doctrine CLI, database platform, and nearest reviewed migration.
3. Read the final mapped record and passing repository tests.
4. Inspect the migration base class, release migration configuration or manifest, current schema status, and target database identity.
5. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
6. Restate the exact authorized tables, columns, indexes, constraints, and data transformation. Stop if any item is unclear.

## Target-project adaptation

Adapt the migration namespace, path, platform guard, SQL, online-change risk, backfill, rollback, and deployment sequence. Never copy Example table names, timestamps, generated columns, credentials, hosts, or migration targets.

## Workflow

1. Run focused repository tests before generating a diff.
2. If stable reference data appears necessary, stop and confirm the exact rows and seeder invocation. Do not embed speculative data in the migration.
3. Run `migrations:diff` from the repository root.
4. Inspect the generated file and every SQL statement. Remove unrelated changes.
5. Use the project's current migration base. In Backendbase MySQL services, use the non-transactional Backendbase base class for new DDL migrations.
6. Add a useful description, platform guard, safe preconditions, and reverse operation when supported.
7. Use `CREATE TABLE IF NOT EXISTS` for an authorized new table, as required by current platform guidance.
8. Update the target project's release migration configuration when releases pin an exact migration. Record rollback compatibility truthfully.
9. Review locks, rolling-deployment compatibility, failure recovery, and data-definition implicit commits.
10. Run a dry run against a prepared target. Apply only with explicit authority for that database.
11. Verify schema and repository behavior after application.

## Backendbase invariants

- Do not add or alter any table, column, index, timestamp, status, audit field, or data outside explicit scope.
- Do not apply a diff that contains an unexplained change. Report it and request a decision.
- Use `Backendbase\Migrations` and `resources/database/Migrations` in an unmodified Backendbase project.
- Treat MySQL DDL as non-transactional and prepare forward recovery.
- Keep Doctrine's migration metadata table visible to the CLI schema filter.
- In an unmodified Backendbase release, update `deployment/release.json` to the latest reviewed migration. Do not edit generated `release-manifest.json` by hand.
- Set `applicationRollbackSafe` to true only when the previous application can safely use the new schema.
- Route approved stable reference data through the seeder workflow. Never seed environment, fixture, tenant, user, or secret data.
- Do not run migration commands from outside the repository root.
- Do not run a mutating migration against an unspecified or shared database. For production, require explicit authority and the approved deployment and recovery workflow.

## Verification

Run focused repository tests, migration status, dry-run SQL, schema validation, PHPStan, and PHPCS. When release migration metadata changed, run the release or deployment-script checks. Run the actual migration only on the explicitly approved prepared database.

## Completion report

Report the exact schema delta, generated and removed SQL, seeder decision, release migration target, rollback policy, platform and transaction decisions, recovery path, target used, commands run, and skipped checks.
