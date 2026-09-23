# Shared Primitives

Put a value in Shared only when its meaning is stable across bounded contexts. Keep business-specific status, limits, roles, and decisions inside their owning context.

## Current patterns

- `EntityId` creates and parses UUIDv7 values.
- `EntityId::null()` is an all-zero sentinel and needs an explicit contract meaning.
- `Email` validates syntax but does not trim or lowercase.
- `Name` checks a two-byte minimum but preserves whitespace.
- `PasswordHash` uses Argon2id for replacement and verification.
- `Pagination` carries page state and serializes `pageSize`, `page`, and `total`.
- `Pagination` rejects non-positive page sizes and offsets greater than `PHP_INT_MAX` at construction. Example HTTP endpoints require positive page numbers and return 400 for invalid offsets.
- `Filter` carries query criteria but does not validate SQL field names.

Repositories must allowlist filter columns and bind values. Never use client field names directly in SQL.

Current gaps include unvalidated coordinate ranges, missing email normalization, and an unimplemented password validation method.

Test construction boundaries, invalid values, exact serialization, and public exception types.

## Time

`Shared/Time/Clock::now()` returns a native `DateTimeImmutable` in UTC. Inject the clock where current time controls availability, expiry, retries, message age, or retention. Supply the instant to pure value calculations.

`Shared/Helpers/DateTimeImmutable::create()` remains available for date construction and audit timestamps. Its default time zone is UTC. An explicit time zone or an offset embedded in the date string retains its existing meaning. Audit-only aggregate and event timestamps still use this helper.

Basis: `resources/docs/16-shared-primitives-and-object-mapping.html`.
