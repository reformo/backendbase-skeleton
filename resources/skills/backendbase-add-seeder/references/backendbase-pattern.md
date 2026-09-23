# Backendbase seeder pattern

## Role-to-target mapping

| Role | Backendbase target |
| --- | --- |
| Seeder class | `resources/database/Seeders` |
| Seeder namespace | `Backendbase\Seeders` |
| Autoload mapping | Composer `autoload.psr-4` |
| Optional release invocation | `postUp()` of an approved migration after queued schema SQL |
| Idempotency evidence | Context Doctrine seeder test |

## Small idempotent example

```php
final class CatalogStatusSeeder
{
    public function seed(Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT code FROM catalog_status WHERE code = :code',
            ['code' => 'active'],
        );
        if ($exists !== false) {
            return;
        }

        $connection->insert('catalog_status', [
            'code' => 'active',
            'label' => 'Active',
        ]);
    }
}
```

The table and values illustrate idempotency roles only. Use only rows and fields explicitly approved for the target project.

## Migration invocation

```php
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE catalog_status (...)');
}

public function postUp(Schema $schema): void
{
    (new CatalogStatusSeeder())->seed($this->connection);
}
```

Doctrine executes SQL queued by `addSql()` after `up()` returns. Run a seeder from `postUp()` when it reads a table created by that SQL.

Call a seeder only when the migration owns both the approved schema and reference-data release. Do not add a migration only to call a seeder unless the user requested that release path.

## Idempotency test shape

```php
$seeder->seed($connection);
$seeder->seed($connection);

self::assertSame(1, (int) $connection->fetchOne(
    "SELECT COUNT(*) FROM catalog_status WHERE code = 'active'",
));
```

Also assert the approved field values. A count alone does not prove correct data.

## Current source behavior and limitations

- Composer maps `Backendbase\Seeders\` to `resources/database/Seeders`.
- `EntrySeeder` performs a parameterized existence check and inserts one lookup row.
- It is intentionally not invoked by any migration.
- It uses UUIDv4 and Example-specific values. Neither choice is a general platform rule.
- The repository test runs the seeder twice and asserts a single row.
- Seeders do not replace test fixtures or runtime configuration.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Adapters/Persistence/Doctrine/CatalogStatusSeederTest.php
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Adapters/Persistence/Doctrine
```

## Source provenance

- `resources/docs/9-persistence-and-database.html`
- `resources/platform/10-schema-changes.md`
- `composer.json`
- `resources/database/Seeders/EntrySeeder.php`
- `src/Backendbase/Domain/ExampleCatalog/Tests/Adapters/Persistence/Doctrine/EntrySeederTest.php`
