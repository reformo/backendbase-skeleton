# Shared Primitives

Put a value in Shared only when its meaning is stable across bounded contexts. Keep business-specific status, limits, roles, and decisions inside their owning context.

## Current patterns

- `EntityId` creates and parses UUIDv7 values.
- `EntityId::null()` is an all-zero sentinel and needs an explicit contract meaning.
- `Email` validates syntax but does not trim or lowercase.
- `Name` checks a two-byte minimum but preserves whitespace.
- `PasswordHash` uses Argon2id for replacement and verification.
- `Pagination` carries page state and serializes `pageSize`, `page`, and `total`.
- `Filter` carries query criteria but does not validate SQL field names.

Repositories must allowlist filter columns and bind values. Never use client field names directly in SQL.

Current gaps include zero page-size acceptance, unvalidated coordinate ranges, missing email normalization, and an unimplemented password validation method.

Test construction boundaries, invalid values, exact serialization, and public exception types.

Basis: `resources/docs/16-shared-primitives-and-object-mapping.html`.
