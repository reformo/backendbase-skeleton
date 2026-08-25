# Backendbase object-mapping pattern

## Role-to-target map

| Mapping role | Backendbase component | Target equivalent |
| --- | --- | --- |
| Sanitizer | `PayloadSanitizer` | Target boundary normalization |
| Mapper | Valinor-backed `ObjectMapper` | Target mapper and strictness settings |
| Target | Public promoted DTO fields | Target accepted input contract |
| Cache | Filesystem and file-watching caches | Target environment cache policy |
| Error | `InvalidUserInput` context | Target stable validation response |

## Mapping flow

```text
boundary array -> PayloadSanitizer -> optional normalizer
-> public-field filter -> Valinor permissive mapper -> typed object
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

$input = $objectMapper->map(
    CreateUserInput::class,
    $payload,
    static function (array $data): array {
        $data['name'] = trim((string) $data['name']);

        return $data;
    },
);
```

The target's public declared properties define the surviving input keys. Backendbase maps with permissive types, so the boundary must reject scalar coercion when it changes the contract.

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

- Unknown payload keys are silently removed through `array_intersect_key`.
- `allowPermissiveTypes()` can hide scalar mistakes.
- Field discovery uses `get_class_vars()`, so private target properties do not survive filtering.
- Development uses a file-watching cache. Other environments use the filesystem cache directly.
- Choose explicit validation or construction when these behaviors do not match the target contract.

## Exact source provenance

- `src/Backendbase/Shared/Services/ObjectMapper.php`
- `config/dependencies/application.php`
- `tests/Shared/Services/MappedInput.php`
- `tests/Shared/Services/SharedServicesTest.php`
- `resources/platform/23-object-mapping.md`

The target payload contract, not the example DTO, decides accepted fields and coercion.
