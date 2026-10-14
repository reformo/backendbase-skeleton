# Backendbase object-mapping pattern

## Role-to-target map

| Mapping role | Backendbase component | Target equivalent |
| --- | --- | --- |
| Normalizer | Explicit optional callback | Approved target normalization exception |
| Mapper | Valinor-backed `ObjectMapper` | Target mapper and strictness settings |
| Target | Typed constructor parameters | Target accepted input contract |
| Cache | Filesystem and file-watching caches | Target environment cache policy |
| Error | `InvalidUserInput` context | Target stable validation response |

## Mapping flow

```text
validated boundary array -> optional documented normalizer
-> strict Valinor mapper -> typed object
                        -> InvalidUserInput on MappingError
```

A small target and call are:

```php
final readonly class CreateUserInput
{
    public function __construct(public string $name, public int $age)
    {
    }
}

$input = $objectMapper->map(CreateUserInput::class, $payload);
```

Constructor parameters define accepted input fields. Backendbase rejects unknown fields and incorrect scalar types. A normalization callback is an explicit contract exception.

## Error contract

A mapping failure becomes a stable invalid-input exception with this conceptual context:

```php
[
    'target' => 'CreateUserInput',
    'errors' => ['age' => 'field-specific message'],
]
```

Do not expose Valinor stack traces, mapper paths, or internal exception objects to clients.

## Current source limitations

- Unknown fields and scalar mismatches produce safe field-indexed errors.
- Input text remains unchanged without an explicit normalizer.
- Constructor parameters support private immutable input state.
- Development uses a file-watching cache. Other environments use the filesystem cache directly.
- Choose explicit validation or construction when these behaviors do not match the target contract.

## Exact source provenance

- `src/Backendbase/Infrastructure/Adapters/Mapping/ObjectMapper.php`
- `config/dependencies/application.php`
- `tests/Infrastructure/Adapters/Mapping/MappedInput.php`
- `tests/Infrastructure/Adapters/Mapping/ObjectMapperTest.php`
- `resources/platform/23-object-mapping.md`

The target payload contract, not the example DTO, decides accepted fields and coercion.
