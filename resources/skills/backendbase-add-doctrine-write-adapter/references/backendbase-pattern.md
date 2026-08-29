# Backendbase Doctrine write pattern

## Role-to-target mapping

| Role | Target |
| --- | --- |
| Aggregate persistence capability | Context `Contracts` interface |
| ORM record | `Adapters/Persistence/Doctrine/Entity` |
| ORM port implementation | `Adapters/Persistence/Doctrine` |
| Production binding | Context `ServiceProvider.php` |
| Mapping and repository evidence | Context-owned tests; existing root adapter-aware tests remain source evidence |
| Schema change | A separately reviewed migration |

## Port and mapping shape

```php
interface CatalogItemWriteRepository
{
    public function add(CatalogItem $item): void;

    public function get(CatalogItemId $itemId): CatalogItem;

    public function save(CatalogItem $item): void;
}
```

```php
final class CatalogItemRecord
{
    private string $uuid;
    private string $name;

    public static function fromDomain(CatalogItem $item): self
    {
        $record = new self();
        $record->synchronize($item);

        return $record;
    }

    public function synchronize(CatalogItem $item): void
    {
        $state = $item->snapshot();
        $this->uuid = $state['uuid'];
        $this->name = $state['name'];
    }

    public function toDomain(): CatalogItem
    {
        return CatalogItem::reconstitute($this->uuid, $this->name);
    }
}
```

Add Doctrine attributes and fields only for the authorized target schema.

For a native string-backed enum, current Backendbase uses the Doctrine enum column type plus `enumType`:

```php
#[Column(type: Types::ENUM, length: 32, enumType: CatalogItemType::class)]
private CatalogItemType $type;
```

`CatalogItemType` is illustrative. Preserve the target project's enum class, column length, database platform, and supported Doctrine mapping. Do not convert a business enum to a string property only for persistence.

## Adapter decisions

- `add()` maps a new aggregate and persists it.
- `get()` filters according to the aggregate's active or removal contract and reconstitutes it.
- `save()` loads the current record, synchronizes domain state, and flushes.
- Translate absence to the project's stable not-found exception.
- Keep uniqueness and concurrency behavior explicit in schema and tests.
- Translate database constraint failures into the same context-safe error used by other adapters.
- Run one behavioral repository contract suite unchanged against Doctrine and memory adapters.

## Current source behavior and limitations

- `ExampleRecord` demonstrates UUID, enum, JSON, generated columns, timestamps, soft removal, indexes, and active-record uniqueness. These are Example-specific choices.
- `PathFinder::doctrineEntityPaths()` discovers direct context entity directories and one nested level.
- The Doctrine CLI schema filter currently collects table names only from direct context entity directories. Prefer direct context placement unless that CLI logic changes too.
- The Example repository test builds an isolated SQLite schema from production metadata. This proves ORM mapping and many repository behaviors, but it does not prove every MySQL generated-column, lock, or migration behavior.
- The Example repository contract suite runs unchanged against Doctrine and memory adapters.
- Mapping changes do not authorize a migration. Generate one only after repository behavior is final and schema scope is approved.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Adapters/Persistence/Doctrine
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/ServiceProviderTest.php
vendor/bin/phpunit tests/Architecture
bin/doctrine orm:validate-schema
composer phpstan
composer complexity
composer cs-check
```

The Doctrine command needs a prepared database and target configuration.

For new movable context tests in an unmodified Backendbase project, use the context `Tests` root. Existing root `tests/Domain/ExampleBoundedContext` files remain adapter-aware source evidence; do not move them during unrelated work.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/9-persistence-and-database.html`
- `resources/platform/09-persistence.md`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/ExampleWriteRepository.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/ExampleWriteRepository.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/Entity/ExampleRecord.php`
- `src/Backendbase/Domain/ExampleBoundedContext/ServiceProvider.php`
- `src/Backendbase/Shared/Helpers/PathFinder.php`
- `config/dependencies/doctrine.php`
- `bin/doctrine`
- `tests/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/DoctrineExampleRepositoryTestCase.php`
- `tests/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/ExampleRepositoryTest.php`
