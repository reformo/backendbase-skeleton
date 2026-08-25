# Backendbase shared-primitive pattern

## Role-to-target map

| Primitive role | Backendbase example | Target equivalent |
| --- | --- | --- |
| Entity identifier | UUIDv7 `EntityId` | Target identifier and persistence format |
| Validated string | `Email` or `Name` | Target syntax and normalization contract |
| Query value | `Pagination` or `Filter` | Target read-boundary contract |
| Sensitive value | `PasswordHash` | Target secret-handling primitive |
| Serialization test | Shared primitive tests | Target public representation test |

## Ownership test

| Shared candidate | Keep in a bounded context |
| --- | --- |
| UUID wrapper | Order status |
| Email syntax | Customer eligibility |
| Date interval constant | Subscription renewal policy |
| Generic pagination | Product search limits |
| Stable transport health value | Role or privilege decision |

Two usages alone do not justify Shared ownership. The meaning and invariants must remain the same across contexts.

A minimal value shape is:

```php
final readonly class PortNumber implements Stringable
{
    public function __construct(private int $value)
    {
        if ($value < 1 || $value > 65535) {
            throw InvalidPortNumber::create('The port number is invalid.');
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
```

Choose methods that express real behavior. Do not add generic getters, setters, or configurability without a caller.

## Current catalog and limitations

- `EntityId` creates and parses UUIDv7 values. Its all-zero `null()` sentinel needs an external contract meaning.
- `Email` validates syntax but does not trim or lowercase.
- `Name` checks a two-byte minimum but preserves whitespace.
- `Coordinates` does not validate numeric form or geographic range.
- `Pagination` accepts a zero page size, which can cause division by zero.
- `Filter` does not validate SQL field names.
- `PasswordHash::validatePassword()` is empty.
- These are known gaps, not templates for new validation.

## Exact source provenance

- `src/Backendbase/Shared/Primitives/Identifier/EntityId.php`
- `src/Backendbase/Shared/Primitives/Email.php`
- `src/Backendbase/Shared/Primitives/Name.php`
- `src/Backendbase/Shared/Primitives/Pagination.php`
- `src/Backendbase/Shared/Primitives/Filter.php`
- `src/Backendbase/Shared/Primitives/Coordinates.php`
- `src/Backendbase/Shared/Primitives/PasswordHash.php`
- `tests/Shared/Primitives/PrimitiveValueObjectsTest.php`
- `tests/Shared/Primitives/PaginationAndHealthCheckTest.php`
- `resources/platform/22-shared-primitives.md`

The target domain and public contracts decide the actual primitive meaning.
