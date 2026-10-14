# Persistence

The domain does not know Doctrine. Context-owned ports separate behavior from storage.

## Write side

- Write handlers depend on a write repository port.
- Doctrine ORM records map typed aggregate snapshots to columns.
- Derive each mapped Doctrine entity class name from its full table name in PascalCase.
- Preserve table prefixes and words: `example_table` maps to `ExampleTable`, and `identity_account` maps to `IdentityAccount`.
- Keep abstract mapped superclass names based on their shared role.
- Records reconstitute aggregates through domain factories.
- Adapters load and save state without business decisions.
- Use native string-backed enums with Doctrine `enumType` mapping.

## Read side

- Query handlers depend on a read repository port.
- Doctrine DBAL adapters use use-case-specific SQL.
- Bind every input parameter.
- Bind page size and offset as integers.
- Validate the complete pagination offset before query dispatch.
- Use stable ordering for pagination.
- Filter removed records explicitly.
- Validate rows before creating immutable read models.
- Never return Doctrine records or raw rows across the port.

Define one repository contract suite for behavior declared by the port. Run it unchanged against memory and Doctrine adapters. Include only applicable behavior, such as lookup, filtering, ordering, pagination, uniqueness, replacement, or removal.

Doctrine repository tests must also use production mapping metadata and Doctrine `SchemaTool`. Test storage mapping, nulls, malformed data, and rollback at this adapter boundary.

Example group queries return `EntryGroupPage`. Both adapters count all matching distinct groups before pagination. Doctrine applies the requested limit and offset in SQL. It does not truncate the result set at 1,000 groups.

Identity and Example records use assigned binary UUID identifiers, read-only virtual UUID projections, and optimistic versions.
New aggregate identifiers use UUIDv7. Example conversion preserves existing public UUIDs, including legacy UUID versions.
Identity mutations use selective Account-first locks.
Reload persisted actor authorization under those locks after an unlocked reference lookup.
Preserve pending Account credential changes and skip database refresh for newly inserted Actors.
See [table ownership](database-table-ownership.md) for scalar references and migration gates.
Identity units create a fresh EntityManager through the platform factory for each eligible retry.
Transaction adapters flush pending ORM state before commit.
An inbox flush failure that closes the EntityManager stops the consumer through `ConsumerRestartRequired`.
The supervisor restarts the process before another message can use the closed unit.

Basis: `resources/docs/1-bounded-contexts.html`, `resources/docs/9-persistence-and-database.html`.

Doctrine framework mutable datetime fields also use UTC microsecond SQL declarations and preserve their PHP mutable type.
This keeps migration metadata compatible with immutable business datetime introspection.
