# Backendbase migration pattern

## Role-to-target mapping

| Role | Backendbase target |
| --- | --- |
| Mapping source | Context Doctrine record |
| Migration namespace | `Backendbase\Migrations` |
| Migration directory | `resources/database/Migrations` |
| Migration configuration | `migrations.json` |
| Doctrine entry point | `bin/doctrine` |
| MySQL DDL base | `Backendbase\Shared\Migrations\BackendbaseAbstractMigration` |
| Release migration source | `deployment/release.json` |
| Generated release manifest | `release-manifest.json` inside the release artifact |
| Approved reference data | `resources/database/Seeders` |
| Repository evidence | Context Doctrine repository tests |

## New migration shape

```php
final class Version20260826000000 extends BackendbaseAbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the approved catalog-item lookup index.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            ! $this->platform instanceof AbstractMySQLPlatform,
            'This migration supports MySQL only.',
        );
        $this->addSql('CREATE INDEX catalog_item_lookup_idx ON catalog_item (lookup_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX catalog_item_lookup_idx ON catalog_item');
    }
}
```

The table, index, version, and description are illustrative. Generate them from the authorized target mapping and current migration clock.

## Review checklist

- The repository behavior and metadata tests pass before diff generation.
- The diff contains only requested objects.
- New table SQL uses `CREATE TABLE IF NOT EXISTS` under current platform guidance.
- Names use database `snake_case`.
- Existing data satisfies new non-null, unique, or foreign-key constraints.
- Long-running locks and rolling-deployment compatibility were reviewed.
- `down()` is safe, or the forward-repair plan is explicit.
- The dry-run target is known and prepared.
- Apply output and resulting schema are recorded.

## Seeder decision gate

Do not add data because the new schema appears to need a default. Confirm each stable reference or lookup row and its identity first. Use the target project's seeder workflow only after explicit approval. Keep the seeder idempotent and test a repeated run.

In an unmodified Backendbase project, seeders live under `resources/database/Seeders` and use namespace `Backendbase\Seeders`. Invoke a requested release-owned seeder from the owning migration only when that invocation is part of the approved change. Do not seed test, environment, tenant, user, credential, or secret data.

## Release-manifest gate

Some target projects pin an exact migration in release metadata. Discover that mechanism before completion. Keep its migration target aligned with the reviewed migration and assess whether the previous application remains compatible with the expanded schema.

Current Backendbase uses `deployment/release.json` as source configuration. `bin/deployment/build-release.sh` requires `migrationTarget` to equal the latest migration and writes it into generated `release-manifest.json`. Set `applicationRollbackSafe` to `true` only when the previous application can use the new schema. Do not change it only to pass the release build. `bin/deployment/deploy-release.sh` dry-runs and applies the manifest target, and it requires a recovery hook before migration.

Edit source release configuration, not generated release artifacts. Adapt the manifest keys and rollout policy to the target project when it uses another release system.

## Current source behavior and limitations

- `migrations.json` sets `all_or_nothing` to false and global `transactional` to true.
- `BackendbaseAbstractMigration` overrides `isTransactional()` to false because MySQL DDL can implicitly commit and break Doctrine savepoints.
- The shared migration base is tested, but the seven existing migration files still extend Doctrine `AbstractMigration`. Treat those files as historical SQL references, not the preferred new base.
- Existing migrations do not consistently use `CREATE TABLE IF NOT EXISTS`; current platform documentation requires it for new tables.
- `bin/doctrine` uses relative paths and must run from the repository root.
- The CLI schema filter discovers direct context service tables and the Doctrine metadata table. Nested context entity discovery is not fully symmetric.
- SQLite metadata tests cannot prove MySQL DDL, generated-column, locking, or deployment behavior.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Adapters/Persistence/Doctrine
bin/doctrine migrations:status
bin/doctrine migrations:diff
reviewedMigration='<exact-reviewed-migration-class>'
bin/doctrine migrations:migrate "$reviewedMigration" --dry-run --no-interaction
bin/doctrine migrations:migrate "$reviewedMigration" --no-interaction
bin/doctrine orm:validate-schema
bash tests/Deployment/deployment-scripts.sh
composer phpstan
composer cs-check
```

`migrations:diff` writes a file. A targeted `migrations:migrate` can also apply pending prerequisite migrations. Inspect the complete status and dry-run plan, and authorize every statement before applying it. For current Backendbase production releases, use the manifest-backed deployment workflow instead of direct Doctrine application.

For new movable repository tests in an unmodified Backendbase project, use the context `Tests` root. Existing root Example repository tests remain source evidence.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/9-persistence-and-database.html`
- `resources/docs/12-deployment-and-operations.html`
- `resources/platform/10-schema-changes.md`
- `resources/platform/18-deployment.md`
- `migrations.json`
- `bin/doctrine`
- `deployment/release.json`
- `deployment/README.md`
- `bin/deployment/build-release.sh`
- `bin/deployment/deploy-release.sh`
- `resources/database/Seeders/ExampleSeeder.php`
- `tests/Deployment/deployment-scripts.sh`
- `src/Backendbase/Shared/Migrations/BackendbaseAbstractMigration.php`
- `tests/Shared/Persistence/Doctrine/DqlAndMigrationTest.php`
- `resources/database/Migrations/Version20260825050000.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/Entity/ExampleRecord.php`
- `tests/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/ExampleRepositoryTest.php`
