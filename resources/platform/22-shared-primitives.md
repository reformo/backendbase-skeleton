# Shared Primitives

Put a value in Shared only when its meaning is stable across bounded contexts. Keep business-specific status, limits, roles, and decisions inside their owning context.
Place context-owned primitive value classes under `<BoundedContext>/Domain/Primitives`.

## Current patterns

- `EntityId` creates and parses UUIDv7 values.
- `EntityId::null()` is an all-zero sentinel and needs an explicit contract meaning.
- `Email` validates syntax but does not trim or lowercase.
- `Name` checks a two-byte minimum but preserves whitespace.
- `PasswordHash` uses Argon2id for replacement and verification.
- `Pagination` carries page state and serializes `pageSize`, `page`, and `total`.
- `Pagination` rejects non-positive page sizes and offsets greater than `PHP_INT_MAX` at construction. Example HTTP endpoints require positive page numbers and return 400 for invalid offsets.
- `Filter` carries query criteria but does not validate SQL field names.
- `ReadinessReport` serializes root `status` and object `checks`. An empty check map remains `{}`.

Repositories must allowlist filter columns and bind values. Never use client field names directly in SQL.

Current gaps include unvalidated coordinate ranges, missing email normalization, and an unimplemented password validation method.

Test construction boundaries, invalid values, exact serialization, and public exception types.

Database JSON columns with non-list contracts use immutable context primitives by default, subject to [explicit mapping exceptions](09-persistence.md#json-column-policy).
List columns retain validated list representations. Do not infer a root shape from an empty PHP array.

Identity state primitives live in `IdentityAndAccess/Domain/Primitives` because their meaning belongs to that context.
`AccountState`, `ActorState`, and `ContactState` are immutable and implement `JsonSerializable`.
They validate array reconstruction and export explicit storage arrays. See [identity state mapping](09-persistence.md#typed-identity-json-state).
Their typed readonly fields qualify for the immutable-state exception to the two-property limit.


## Time

`Shared/Time/Clock::now()` returns a native `DateTimeImmutable` in UTC. Inject the clock where current time controls availability, expiry, retries, message age, or retention. Supply the instant to pure value calculations. Example Entry audit mutations also receive explicit clock instants through EntryWrites.

`Shared/Helpers/DateTimeImmutable::create()` remains available for date construction. Its default time zone is UTC. An explicit time zone or an offset embedded in the date string retains its existing meaning. The shared domain-event trait still uses this helper for event occurrence time.

Basis: `resources/docs/16-shared-primitives-and-object-mapping.html`.
