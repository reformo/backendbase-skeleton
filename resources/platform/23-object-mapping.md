# Object Mapping

`ObjectMapper` converts sanitized boundary arrays into typed objects through Valinor.

## Flow

1. Sanitize the untrusted payload.
2. Apply an optional normalization callback.
3. Read public declared fields from the target class.
4. Remove keys that do not match those fields.
5. Map with permissive Valinor types.
6. Translate `MappingError` into `InvalidUserInput`.

The public error context contains the short target class name and errors indexed by field name. Do not expose Valinor internals to controllers or clients.

Unknown input keys are silently removed. Permissive mapping can also hide scalar mistakes.

Use explicit boundary validation when unknown keys or exact scalar types matter. Keep target public fields equal to the intended input contract.

Development uses a file-watching Valinor cache. Other modes use the filesystem cache directly.

Basis: `resources/docs/16-shared-primitives-and-object-mapping.html`.
