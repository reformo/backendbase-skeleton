# Backendbase Doctrine read pattern

## Role-to-target mapping

| Role | Target |
| --- | --- |
| Query criteria | Context query contract |
| Result contract | Context read model, page, scalar, or list |
| Read capability | Context read-port interface |
| SQL implementation | `Adapters/Persistence/Doctrine` |
| Persisted-row validation | A dedicated mapper beside the adapter |
| Production binding | Context `ServiceProvider.php` or target composition root |
| Evidence | Context-owned mapper, repository, composition, and lifecycle tests |

## Parameterized projection shape

```php
final readonly class CatalogItemReadRepository implements CatalogItemReadRepositoryContract
{
    public function __construct(private Connection $connection)
    {
    }

    public function get(GetCatalogItem $query): CatalogItemDetails|null
    {
        $row = $this->connection->fetchAssociative(
            'SELECT uuid, name, created_at FROM catalog_item WHERE uuid = :uuid LIMIT 1',
            ['uuid' => $query->itemId()->toString()],
        );

        return $row === false ? null : CatalogItemReadModelMapper::details($row);
    }
}
```

The table and fields illustrate roles only. Use the target schema and visibility contract.

## Mapper rules

- Check key presence and concrete scalar representation.
- Convert only persisted representations explicitly accepted by the storage contract.
- Validate enum values with `tryFrom()` and reject unknown values.
- Decode JSON with `JSON_THROW_ON_ERROR` and validate object or list shape.
- Parse dates through the target project's date helper and preserve the previous exception.
- Return immutable context read models.

## Query safety

- Select explicit columns. Never use `SELECT *` in an application projection.
- Bind all values.
- For list criteria such as `IN`, use the target DBAL list parameter type, such as `ArrayParameterType`. Never interpolate a comma-separated list.
- Bind `LIMIT` and `OFFSET` with `ParameterType::INTEGER`.
- Use stable ordering such as a business key plus a unique tie-breaker.
- Allowlist dynamic filter and sort columns before adding them to SQL.
- Preserve existing table, column, constraint, and index identifiers.
- Keep one executable statement in each DBAL call.
- Distinguish `null`, empty list, and empty page according to the query contract.
- Filter soft-removed or hidden rows explicitly when the contract requires it.

## Cardinality and SQL decisions

Choose the DBAL operation from the declared result:

| Expected result | Suitable DBAL operation |
| --- | --- |
| One scalar or absence | `fetchOne()` |
| One row or absence | `fetchAssociative()` |
| Zero or more rows from one projected column | `fetchFirstColumn()` |
| Many rows | `fetchAllAssociative()` |

Use `executeQuery()` when statement-level control is useful, not as a universal wrapper. Treat `false` from one-value and one-row fetches according to the port's declared absence semantics. Map every returned row before it crosses the port.

Keep SQL keywords and explicit column lists readable. Use named parameters for dynamic values. Do not rename existing schema identifiers, change semantics, or reformat unrelated statements only to apply a preferred layout.

## Composition root

Bind the context read-port interface to the DBAL adapter in the target composition root. An adapter unit test does not prove production reachability. Load the real provider or a faithful production container and resolve the port, adapter, and constructor dependencies.

## Current source behavior and limitations

- `ExampleReadRepository` uses DBAL and purpose-specific SQL against the same table as the write adapter.
- It binds page size and offset as integers and orders pages by `created_at, id`.
- `ExampleReadModelMapper` rejects invalid strings, integers, booleans, enums, dates, and JSON objects.
- The shared Example repository contract checks read results, filtering, ordering, pagination, and absence against memory and Doctrine adapters.
- The memory adapter does not prove DBAL implementation details. Doctrine-specific repository tests remain required.
- Existing SQLite metadata tests do not prove MySQL-specific index or optimizer behavior.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Adapters/Persistence/Doctrine
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/ServiceProviderTest.php
vendor/bin/phpunit tests/Functional/CatalogLifecycleTest.php
composer phpstan
composer complexity
composer cs-check
```

For movable context tests in an unmodified Backendbase project, use the context `Tests` root. Keep platform and integration tests under root `tests`.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/9-persistence-and-database.html`
- `resources/platform/09-persistence.md`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/ExampleReadRepository.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Contracts/ReadModel`
- `src/Backendbase/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/ExampleReadRepository.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/ExampleReadModelMapper.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Adapters/Persistence/Doctrine/ExampleReadModelMapperTest.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Adapters/Persistence/Doctrine/ExampleRepositoryTest.php`
