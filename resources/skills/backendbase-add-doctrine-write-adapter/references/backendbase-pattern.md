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
final class CatalogItem
{
    private string $uuid;
    private string $name;

    public static function fromDomain(DomainCatalogItem $item): self
    {
        $record = new self();
        $record->synchronize($item);

        return $record;
    }

    public function synchronize(DomainCatalogItem $item): void
    {
        $state = $item->snapshot();
        $this->uuid = $state->uuid();
        $this->name = $state->name();
    }

    public function toDomain(): DomainCatalogItem
    {
        return DomainCatalogItem::reconstitute($this->uuid, $this->name);
    }
}
```

The entity example maps `catalog_item` and uses `CatalogItem` as its class name. Import the target domain aggregate as `DomainCatalogItem` to avoid a name collision.

The example assumes `snapshot()` returns a context-owned object with typed accessors. Add Doctrine attributes and fields only for the authorized target schema.

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

In an unmodified Backendbase project, business table names have no bounded-context prefix. `example_table` is the sole exception.
Derive each mapped entity class name from its full table name in PascalCase. For example, `example_table` maps to `ExampleTable`, and `account` maps to `Account`.
Adapt this convention to the target project. Record ownership separately and check for shared-database name collisions.

Each entity declares all mapped fields directly, including identifiers, versions, and timestamps.
Entity classes have no parent class. Do not hide mapped fields in traits or mapped superclasses.
Interfaces and behavior-only traits can supply shared contracts or methods.
Adapt identifiers, columns, and mapping behavior to the target schema.

- `ExampleTable` demonstrates UUID, enum, JSON, generated columns, timestamps, soft removal, indexes, and active-record uniqueness. These are Example-specific choices.
- `PathFinder::doctrineEntityPaths()` discovers direct context entity directories and one nested level.
- The Doctrine CLI schema filter currently collects table names only from direct context entity directories. Prefer direct context placement unless that CLI logic changes too.
- The Example repository test builds an isolated SQLite schema from production metadata. This proves ORM mapping and many repository behaviors, but it does not prove every MySQL generated-column, lock, or migration behavior.
- The Example repository contract suite runs unchanged against Doctrine and memory adapters.
- Mapping changes do not authorize a migration. Generate one only after repository behavior is final and schema scope is approved.

## JSON column default and exceptions

In an unmodified Backendbase project, non-list database JSON columns use immutable context primitives with `JsonSerializable`, `fromArray()`, and `toArray()` by default.
Doctrine fields stay arrays. Typed accessors reconstruct values and replacement methods store arrays. DBAL and seeders use the same contract.
List columns retain validated lists. Preserve empty objects, empty lists, and nested shapes; classify from the declared contract.

- Identity `state` columns use `AccountState`, `ActorState`, and `ContactState` under `IdentityAndAccess/Domain/Primitives`.
- `example_table.details` is an explicit exception. `EntryDetails` and `JSON_OBJECT` preserve open-ended content, nested shapes, and legacy empty-list read compatibility.
- `integration_event_outbox.payload` is an explicit exception. The shared relay preserves versioned event payloads without reconstructing business classes.

Each exception requires ownership, a reason, mapping, validation, compatibility, and tests.
These identifiers describe current Backendbase source. Discover equivalent roles and explicit exceptions in the target project.
See the [complete policy](../../../platform/09-persistence.md#json-column-policy).

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

For movable context tests in an unmodified Backendbase project, use the context `Tests` root. Keep platform and integration tests under root `tests`.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/9-persistence-and-database.html`
- `resources/platform/09-persistence.md`
- `src/Backendbase/Domain/ExampleCatalog/Contracts/EntryWriteRepository.php`
- `src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Doctrine/EntryWriteRepository.php`
- `src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Doctrine/Entity/ExampleTable.php`
- `src/Backendbase/Domain/ExampleCatalog/ServiceProvider.php`
- `src/Backendbase/Shared/Helpers/PathFinder.php`
- `config/dependencies/doctrine.php`
- `tests/Infrastructure/Adapters/Persistence/Doctrine/Mapping/EntityFieldOwnershipTest.php`
- `bin/doctrine`
- `src/Backendbase/Domain/ExampleCatalog/Tests/Adapters/Persistence/Doctrine/DoctrineEntryRepositoryTestCase.php`
- `src/Backendbase/Domain/ExampleCatalog/Tests/Adapters/Persistence/Doctrine/EntryRepositoryTest.php`
