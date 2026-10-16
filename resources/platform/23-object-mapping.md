# Object Mapping

`Backendbase\Infrastructure\Adapters\Mapping\ObjectMapper` converts validated boundary arrays into typed objects through Valinor.

## Flow

1. Validate the payload shape and size at the inbound boundary.
2. Apply a named normalization callback only for a documented contract exception.
3. Map the complete payload with strict Valinor defaults.
4. Reject unknown fields and incorrect scalar types.
5. Translate `MappingError` into `InvalidUserInput`.

The public error context contains the short target class name and errors indexed by field name.
Error text does not contain submitted values or Valinor internals.
The mapper preserves text when no normalization callback is supplied.

Constructor parameters define the input contract. Domain value objects enforce their own business rules.
JSON enum strings and UUID strings have explicit constructors. Numeric strings do not become integers implicitly.

Example API preserves accepted text without a global sanitizer.
Its POST and PATCH boundaries read bounded JSON objects, reject unknown top-level fields, and require native integer body identifiers.
Context-owned values enforce field limits and valid identifiers before dispatch.
Details require an object with non-integer top-level member names. Nested objects and arrays retain their shapes.
Query parameters retain their declared string-to-integer conversion.

Development uses a file-watching Valinor cache. Other modes use the filesystem cache directly.
These caches contain mapping metadata.

Basis: [the shared primitives guide](../docs/16-shared-primitives-and-object-mapping.html).
