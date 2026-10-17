# Migration Consolidation and Adoption

The consolidated history supports fresh installations only.
Three installation migrations create eleven application tables. A fourth migration removes the identity table prefixes. A fifth migration applies the remaining table, column, and index names. A sixth removes the operator action table while retaining Admin-state evidence.
A sixth migration removes `operator_action`. The final application schema contains ten tables.
It does not upgrade a database that applied the former migration history.

## Consolidation map

| Retained migration | Incorporated changes |
| --- | --- |
| `Version20260823000000` | Original messaging tables; destination and recovery tables from `Version20261014010000`; message cleanup indexes from `Version20261014050000` |
| `Version20260823215059` | Original Example table; final binary UUID schema from `Version20261014030000`; Example query indexes from `Version20261014050000` |
| `Version20261014020000` | Original identity tables; operator evidence table from `Version20261014040000` |

The four incorporated migration files are removed.
The release target is `Backendbase\Migrations\Version20261017173407`.
The Example table uses assigned binary UUID keys, virtual public UUIDs, versions, and microsecond timestamps at creation.
The Example installation creates no shadow table, retained legacy table, data copy, or atomic rename.
Existing identifiers, data types, constraints, indexes, and messaging recovery tables keep their final schema definitions.
The final forward migration removes operator names and action timestamps. Admin state retains invitation and approval evidence.
The destination remains nullable for compatibility with the existing message recovery contract.
The identity installation creates required `password_hash VARCHAR(255)` on `identity_account`, which the naming migration renames to `account`.
Existing databases with credential JSON require a separate reviewed hash extraction and schema adoption plan.
Existing Account state JSON using `owned` requires a separate rename to `ownershipVerified` that preserves the boolean value.
The application reads `ownershipVerified` directly. This JSON-key rename adds no migration or database column.

Messaging and identity installation `down()` methods reject removal of recovery and operator evidence.
The explicit operator history removal also rejects reverse execution. Recovery needs the backup and matching application.
Example `down()` drops the Example table. Execute that operation only with explicit authority for its data loss.
The release keeps `applicationRollbackSafe` false. Older applications cannot use the renamed tables; the former integer-key application also cannot use the binary schema.

## Fresh installation

Identify the target database and verify that application tables and prior migration records are absent.
Review all installation table definitions, the operator history removal, and the release migration target.
Review the complete dry-run plan against the prepared target.
Apply only with explicit authority for that target database.
Verify table definitions, ordered index columns, generated UUID projections, timestamp precision, and ORM schema parity.
After partial failure, inspect completed objects before choosing recovery. Do not rerun table creation over existing tables.

## Existing database adoption

Do not rerun the three installation migrations on an existing application schema.
A database already at the exact consolidated `Version20261014020000` schema can use the forward naming migration after target review.
Follow the [table rename procedure](../10-schema-changes.md#remove-bounded-context-table-prefixes).
Review the later [operator history removal](../10-schema-changes.md#remove-operator-history) before advancing to the release target.
Older or divergent histories still require the adoption plan below.
Prepare a target-specific adoption plan outside the fresh-installation history.

1. Identify the database, applied revision, executed migration versions, and recovery checkpoint.
2. Compare every final table, column, default, generated expression, constraint, index, charset, collation, and engine.
3. Inventory retained Example tables and references before changing binary or integer keys.
4. Review missing schema changes and required data conversions as separate forward migrations.
5. Verify row counts, identifiers, relationships, uniqueness, and audit evidence on a prepared clone.
6. Review the exact migration-record reconciliation only after every final object matches.
7. Obtain explicit authority for the target database, schema changes, and version-record changes.

Do not delete historical version records, discard recovery tables, or mark consolidated versions executed automatically.
Do not treat an existing table name as proof of an equivalent schema.
Use [Example conversion planning](example-uuid-conversion.md) when integer Example keys remain.
This repository does not provide an automatic existing-database adoption command.

## Verification

`ConsolidatedSchemaMigrationTest` checks the installation and forward migrations, release target, table groups, and installation safeguards.
`OperatorActionRemovalMigrationTest` checks the exact table removal, platform guard, and irreversible recovery boundary.
`ExampleSchemaMigrationTest` checks the final Example creation statement and its authorized reverse operation.
`IntegrationEventSchemaMigrationTest` checks destination, recovery tables, and messaging index declarations.
`RequiredQueryIndexesTest` checks installation SQL, index column order, ORM metadata, and SQLite query plans.
Prepared MySQL service tests exercise fresh installation, native indexes, ORM round trips, operator workflows, and recovery operations.
SQLite and offline SQL checks do not prove native MySQL execution, generated-column behavior, or optimizer decisions.
