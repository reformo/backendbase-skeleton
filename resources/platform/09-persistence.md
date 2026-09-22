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
- Validate the complete pagination offset before query dispatch.
- Use stable ordering for pagination.
- Filter removed records explicitly.
- Validate rows before creating immutable read models.
- Never return Doctrine records or raw rows across the port.

Define one repository contract suite for behavior declared by the port. Run it unchanged against memory and Doctrine adapters. Include only applicable behavior, such as lookup, filtering, ordering, pagination, uniqueness, replacement, or removal.

Doctrine repository tests must also use production mapping metadata and Doctrine `SchemaTool`. Test storage mapping, nulls, malformed data, and rollback at this adapter boundary.

Example group queries return `ExampleGroupPage`. Both adapters count all matching distinct groups before pagination. Doctrine applies the requested limit and offset in SQL. It does not truncate the result set at 1,000 groups.

Basis: `resources/docs/1-bounded-contexts.html`, `resources/docs/9-persistence-and-database.html`.
