# Backendbase value-object pattern

## Role-to-target mapping

| Meaning | Target |
| --- | --- |
| One context's status, limit, role, code, or policy | The owning context's `Domain` area |
| Stable UUID, email syntax, or generic pagination meaning | `src/Backendbase/Shared/Primitives` |
| Query response data without an invariant | `Contracts/ReadModel`, not a value object |
| Boundary input shape | The boundary adapter, not the domain |

## Small value example

```php
final readonly class StockKeepingUnit implements Stringable
{
    public function __construct(private string $value)
    {
        if (preg_match('/^[A-Z0-9-]{4,32}$/', $value) !== 1) {
            throw InvalidStockKeepingUnit::create('The stock keeping unit is invalid.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
```

The expression is illustrative. Derive the real invariant from the requested domain contract.

## Decision rules

- State whether input is preserved or normalized.
- Reject invalid input explicitly. Do not silently truncate or coerce it.
- Use a string-backed enum when the value is a closed set without additional behavior.
- Use a value object when construction, comparison, formatting, or behavior protects meaning.
- Keep persistence and HTTP parsing outside the value object, apart from stable scalar conversion.
- Test the public exception type and the exact serialized form.

## Current source behavior and limitations

- `Email` validates syntax but does not trim or lowercase.
- `Name` checks a byte-length minimum and preserves whitespace.
- Shared `EntityId` creates UUIDv7 values; its all-zero `null()` sentinel needs explicit surrounding meaning.
- `Pagination` accepts a zero page size and must not be copied as an invariant example.
- `Coordinates` does not validate numeric format or geographic range.
- `PasswordHash::validatePassword()` is not implemented.

## Verification map

```sh
vendor/bin/phpunit tests/Domain/Catalog/Domain/StockKeepingUnitTest.php
vendor/bin/phpunit tests/Shared/Primitives
composer phpstan
composer cs-check
```

Run only the owning path for the chosen placement.

## Source provenance

- `resources/docs/16-shared-primitives-and-object-mapping.html`
- `resources/platform/22-shared-primitives.md`
- `src/Backendbase/Shared/Primitives/Email.php`
- `src/Backendbase/Shared/Primitives/Name.php`
- `src/Backendbase/Shared/Primitives/Pagination.php`
- `src/Backendbase/Shared/Primitives/Identifier/EntityId.php`
- `tests/Shared/Primitives/PrimitiveValueObjectsTest.php`
- `tests/Shared/Primitives/TestEntityId.php`
