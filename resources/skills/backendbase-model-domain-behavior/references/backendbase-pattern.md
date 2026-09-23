# Backendbase domain-behavior pattern

## Role-to-target mapping

| Concern | Target |
| --- | --- |
| Aggregate and state transitions | `Catalog/Domain` |
| Business enum or value object | `Catalog/Domain` |
| Use-case orchestration | `Catalog/Application` |
| Persistence translation | `Catalog/Adapters/Persistence` |
| Pure behavior tests | The context-owned `Tests/Domain` area |

`Catalog` is illustrative. Use the discovered owning context.

## Small aggregate example

```php
final class CatalogItem
{
    private function __construct(private CatalogItemState $state)
    {
    }

    public static function create(CatalogItemId $id, ItemName $name): self
    {
        return new self(new CatalogItemState($id, $name, false));
    }

    public static function reconstitute(CatalogItemState $state): self
    {
        return new self($state);
    }

    public function discontinue(): void
    {
        if ($this->state->isDiscontinued()) {
            throw ItemAlreadyDiscontinued::create('The item is already discontinued.');
        }

        $this->state = $this->state->discontinued();
    }
}
```

The class and state object are illustrative. Preserve the target project's established state style instead of introducing this shape automatically.

## Decision rules

- Validate external syntax at the boundary, then enforce business invariants in domain objects.
- Use a named factory for new state and a separate factory for persisted state.
- Complete validation before mutation.
- Keep storage snapshots typed. Do not expose public mutable properties.
- Do not put SQL, transaction control, serialization for brokers, or response shaping in the domain.
- Do not create a value object for a primitive that has no rule or domain meaning.

## Current source behavior and limitations

- `Example` is the primary aggregate reference. `IdentityAndAccess/Domain/Account` also implements production aggregate behavior.
- It stores a typed array snapshot and does not extend `Backendbase\Shared\Domain\Aggregate`.
- The shared `Aggregate` event-recording API is exercised by test helpers, not by the production Example aggregate.
- No automatic dispatcher drains `Aggregate::getRecordedEvents()` in the current production flow.
- Example timestamps and soft removal describe that sample model, not every future domain.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Domain
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
```

## Source provenance

- `resources/docs/1-bounded-contexts.html`
- `resources/platform/02-architecture.md`
- `resources/platform/03-bounded-contexts.md`
- `src/Backendbase/Domain/ExampleBoundedContext/Domain/Example.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Domain/ExampleType.php`
- `src/Backendbase/Shared/Domain/Aggregate.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Domain/ExampleTest.php`
- `tests/Shared/Domain/SharedDomainSupportTest.php`
- `tests/Architecture/DomainPurityTest.php`
- `tests/Architecture/FrameworkImportBoundaryTest.php`
