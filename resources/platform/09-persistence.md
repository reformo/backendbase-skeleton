# Persistence

The domain does not know Doctrine. Context-owned ports separate behavior from storage.

## Write side

- Write handlers depend on a write repository port.
- Doctrine ORM records map aggregate snapshots to columns.
- Records reconstitute aggregates through domain factories.
- Adapters load and save state without business decisions.
- Use native string-backed enums with Doctrine `enumType` mapping.

## Read side

- Query handlers depend on a read repository port.
- Doctrine DBAL adapters use use-case-specific SQL.
- Bind every input parameter.
- Bind page size and offset as integers.
- Use stable ordering for pagination.
- Filter removed records explicitly.
- Validate rows before creating immutable read models.
- Never return Doctrine records or raw rows across the port.

Define one repository contract suite for behavior that every adapter must provide. Run it unchanged against memory and Doctrine adapters. Include active uniqueness, soft removal, replacement, lookup, filtering, ordering, and pagination behavior.

Doctrine repository tests must also use production mapping metadata and Doctrine `SchemaTool`. Test storage mapping, nulls, malformed data, and rollback at this adapter boundary.

Basis: `resources/docs/1-bounded-contexts.html`, `resources/docs/9-persistence-and-database.html`.
