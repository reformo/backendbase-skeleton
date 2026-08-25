# Backendbase query pattern

## Role-to-target mapping

| Role | Target |
| --- | --- |
| Query criteria | `Catalog/Contracts/Query` |
| Composite result | `Catalog/Contracts/ReadModel` |
| Read capability | A context-owned read port |
| Query orchestration | `Catalog/Application/QueryHandlers` |
| SQL and persisted-row mapping | `Catalog/Adapters/Persistence/Doctrine` |

`Catalog` is illustrative. Use the discovered owning context.

## Minimal query

```php
/** @implements Query<CatalogItemDetails|null> */
#[CQRSHandler(GetCatalogItemHandler::class)]
final readonly class GetCatalogItem implements Query
{
    public function __construct(private CatalogItemId $itemId)
    {
    }

    public function itemId(): CatalogItemId
    {
        return $this->itemId;
    }

    public function jsonSerialize(): array
    {
        return ['itemId' => $this->itemId->toString()];
    }
}
```

## Minimal handler

```php
/** @implements QueryHandler<GetCatalogItem, CatalogItemDetails|null> */
final readonly class GetCatalogItemHandler implements QueryHandler
{
    public function __construct(private CatalogItemReadRepository $repository)
    {
    }

    /** @param GetCatalogItem $query */
    public function handle(Query $query): CatalogItemDetails|null
    {
        return $this->repository->get($query);
    }
}
```

## Result decisions

- Use `null` only when absence is part of the query contract.
- Use an empty list or page when the collection exists but has no members.
- Use a scalar only when it remains an intentional stable contract.
- Use immutable read models for composite data.
- Keep transport response decoration outside the read model.

## Current source behavior and limitations

- The query bus reads the first handler attribute and positional argument index `0`.
- `Query<TResult>` and `QueryHandler<TQuery, TResult>` are PHPStan contracts. Runtime dispatch returns `mixed`.
- Current query contracts expose `toArray()` even though the shared `Query` interface requires only `jsonSerialize()`.
- Current production handlers live in `Application/QueryHandlers` and delegate directly to the read port.
- Query paths do not use aggregates or integration events.
- The current source has no dedicated unit test for each trivial query handler. Contract, adapter, and lifecycle tests provide stronger evidence.

## Verification map

```sh
vendor/bin/phpunit tests/Domain/Catalog/Contracts
vendor/bin/phpunit tests/Domain/Catalog/Adapters/Persistence/Doctrine
vendor/bin/phpunit tests/Functional/CatalogLifecycleTest.php
composer phpstan
composer cs-check
```

## Source provenance

- `resources/docs/2-cqrs.html`
- `resources/platform/04-cqrs.md`
- `src/Backendbase/Shared/CQRS/Query.php`
- `src/Backendbase/Shared/CQRS/QueryHandler.php`
- `src/Backendbase/Shared/CQRS/ContainerAwareQueryBus.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/Query`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/ReadModel`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/ExampleReadRepository.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Application/QueryHandlers`
- `tests/Domain/ExampleBoundedContext/Contracts/CommandAndQueryContractsTest.php`
- `tests/Functional/ExampleLifecycleTest.php`
