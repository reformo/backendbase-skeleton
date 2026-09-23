# Backendbase memory-adapter pattern

## Role-to-target mapping

| Role | Target |
| --- | --- |
| Shared in-process state | `Adapters/Persistence/Memory` store |
| Write-port implementation | Memory write adapter |
| Read-port implementation | Memory read adapter |
| Test bindings | The focused test container |
| Use-case evidence | Context service and functional lifecycle tests |

## Clone-safe store

```php
final class CatalogItemStore
{
    /** @var array<string, CatalogItem> */
    private array $items = [];

    public function save(CatalogItem $item): void
    {
        $this->items[$item->id()->toString()] = clone $item;
    }

    public function get(CatalogItemId $itemId): CatalogItem|null
    {
        $item = $this->items[$itemId->toString()] ?? null;

        return $item === null ? null : clone $item;
    }

    /** @return list<CatalogItem> */
    public function all(): array
    {
        return array_map(static fn (CatalogItem $item): CatalogItem => clone $item, $this->items);
    }
}
```

Clone mutable aggregates. Immutable aggregate designs can use the target project's established approach.

## Parity checklist

- The same port method returns the same result category.
- Missing and removed records produce the same `null` or exception behavior.
- Filters compare the same typed values.
- Ordering is explicit and deterministic.
- Pagination uses the same offset and page-size contract.
- Read models contain the same public data and types.
- Save and reload do not share an accidental mutable reference.
- The same behavioral repository contract suite passes against memory and production adapters.

## Current source behavior and limitations

- Example memory read and write adapters share `EntryStore`.
- `EntryStore` clones values on save and read.
- `EntryStore` enforces active lookup identity uniqueness with the same context-safe error as Doctrine.
- The functional lifecycle test binds memory adapters to the production ports and uses real container-aware buses.
- The shared Example repository contract checks sorted groups, pagination, active uniqueness, soft removal, and replacement.
- Memory tests do not prove Doctrine metadata, SQL binding, database constraint enforcement, rollback, or MySQL behavior.
- In an unmodified Backendbase project, Example group pagination uses the same complete distinct-group total and page contract in both adapters. Account memory adapters execute lock callbacks sequentially; they do not simulate cross-process database locks.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Application
vendor/bin/phpunit tests/Functional/CatalogLifecycleTest.php
composer phpstan
composer complexity
composer cs-check
```

## Source provenance

- `resources/docs/1-bounded-contexts.html`
- `resources/docs/10-testing-and-quality.html`
- `resources/platform/17-testing.md`
- `src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Memory/EntryStore.php`
- `src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Memory/EntryWriteRepository.php`
- `src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Memory/EntryReadRepository.php`
- `src/Backendbase/Domain/ExampleCatalog/Tests/EntryServiceTest.php`
- `tests/Functional/EntryLifecycleTest.php`
