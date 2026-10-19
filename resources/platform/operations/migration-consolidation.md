# Migration Consolidation and Adoption

The consolidated history supports fresh installations only.
Four installation migrations create eleven application tables directly with their final table, column, and index names.
It does not upgrade a database that applied the former migration history.

## Consolidation map

| Retained migration | Incorporated changes |
| --- | --- |
| `Version20260823000000` | Original messaging tables; destination and recovery tables from `Version20261014010000`; cleanup indexes from `Version20261014050000`; final claim and failure column names from `Version20261017010000` |
| `Version20260823215059` | Original Example table; final binary UUID schema from `Version20261014030000`; Example query indexes from `Version20261014050000` |
| `Version20261014020000` | Identity and contact verification tables; final table and index names from `Version20261017000000` and `Version20261017010000` |
| `Version20261018000000` | Durable HTTP request claims, encrypted responses, and expiry index |

Former staged and naming migration files are removed.
The release target is `Backendbase\Migrations\Version20261018000000`.
HTTP replay remains a separate installation group. See [HTTP idempotency](http-idempotency.md).
The Example table uses assigned binary UUID keys, virtual public UUIDs, versions, and microsecond timestamps at creation.
The Example installation creates no shadow table, retained legacy table, data copy, or atomic rename.
All four installation `up()` methods contain only plain `CREATE TABLE` statements. They perform no ALTER, rename, backfill, or data copy.
Existing identifiers, data types, constraints, indexes, and messaging recovery tables keep their final schema definitions.
The migration history creates no operator table. Admin state retains invitation and approval evidence.
The destination remains nullable for compatibility with the existing message recovery contract.
The identity installation creates `account`, `user`, `admin`, and `contact_verification` directly, including required `account.password_hash VARCHAR(255)`.
Existing databases with credential JSON require a separate reviewed hash extraction and schema adoption plan.
Existing Account state JSON using `owned` requires a separate rename to `ownershipVerified` that preserves the boolean value.
The application reads `ownershipVerified` directly. This JSON-key rename adds no migration or database column.

Messaging, identity, and HTTP replay installation `down()` methods reject removal of their retained evidence.
Example `down()` drops the Example table. Execute that operation only with explicit authority for its data loss.
The release keeps `application-rollback-safe` false. Older applications cannot use the renamed tables; the former integer-key application also cannot use the binary schema.

## Fresh installation

Identify the target database and verify that application tables and prior migration records are absent.
Review all installation table definitions and the release migration target.
Review the complete dry-run plan against the prepared target.
Apply only with explicit authority for that target database.
Verify table definitions, ordered index columns, generated UUID projections, timestamp precision, and ORM schema parity.
After partial failure, inspect completed objects before choosing recovery. Do not rerun table creation over existing tables.

## Existing database adoption

Do not rerun installation migrations on an existing application schema.
The earlier consolidated installations also require adoption because the retained version identifiers now create final names directly.
Earlier naming migrations are no longer available as an upgrade path. Review the actual schema instead of inferring it from recorded versions.
Review [earlier operator history](../10-schema-changes.md#operator-history) before adopting the rewritten migration history.
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

`ConsolidatedSchemaMigrationTest` checks all four installation migrations, the release target, table groups, and installation safeguards.
`IdentityContactSchemaTest` compares installation SQL directly with production ORM table and index definitions.
`ExampleSchemaMigrationTest` checks the final Example creation statement and its authorized reverse operation.
`IntegrationEventSchemaMigrationTest` checks destination, recovery tables, and messaging index declarations.
`RequiredQueryIndexesTest` checks installation SQL, index column order, ORM metadata, and SQLite query plans.
Prepared MySQL service tests exercise fresh installation, native indexes, ORM round trips, operator workflows, and recovery operations.
SQLite and offline SQL checks do not prove native MySQL execution, generated-column behavior, or optimizer decisions.

## Native verification on 19 October 2026

The four-migration history passed fresh installation on a new MySQL 8.4.11 container.
The container used temporary memory storage, a dedicated network, and a random localhost port. It mounted no existing data.
All database connections used this new server. Existing databases were not accessed.

| Evidence | Recorded result |
| --- | --- |
| Run identifier | `63e78146db780116` |
| UTC interval | 09:38:19 to 09:38:29, including installation, native tests, and cleanup |
| Target | `127.0.0.1:50537`, database `backendbase_fresh_63e78146db780116` |
| Image | `mysql:8.4.11@sha256:b3b90af2a6552ae30c266fdb7d5dd55f3afb72404bb78d37fe8a23eb857fd3fb` |
| Initial state | Empty application database with no migration records |
| Dry run | Eleven CREATE statements; no application tables created or migration versions recorded |
| Installation | Four migrations executed; eleven application tables and the migration metadata table created |
| Migration status | Four executed, zero unavailable, zero pending; repeated migration execution made no changes |
| Doctrine validation | Five mapped tables; zero mapping errors; zero required schema changes |
| Native schema | Seven virtual UUID projections; microsecond application timestamps; column and index definitions captured |
| Native tests | Seven cases, 76 assertions, zero failures, zero errors, zero skips |
| Cleanup | All secondary test databases, the container, and its dedicated network removed; no persistent volumes created |

The mapped tables are `account`, `admin`, `contact_verification`, `example_table`, and `user`.
The six infrastructure tables use DBAL directly. Doctrine ORM comparison excludes those tables and migration metadata.
Native tests cover Example ORM round trips, required indexes, identity mapping, Admin setup, recovery, and HTTP replay.
The isolated run does not establish production performance or existing-database adoption safety.

Local evidence is under `artifacts/service-verification/migration-consolidation/63e78146db780116`.
It includes target identity, isolation settings, dry-run SQL, migration output, ORM results, native schema definitions, JUnit results, and cleanup records.
