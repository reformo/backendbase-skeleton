# Example UUID Conversion

The conversion preserves each existing public UUID and every HTTP field.
The new primary key is `UUID_TO_BIN(uuid, 0)`. New entries allocate UUIDv7.
The generated public projection is `BIN_TO_UUID(id, 0)`.
The opaque integer `typeTargetId` remains unchanged. No identity reference is invented for that selector.

## Preparation

Inventory the actual schema, migration history, logical references, views, exports, jobs, and stored UUIDs.
Reconcile databases that used the former consolidated Example migrations before deployment.
Require canonical lowercase UUID strings and no duplicate UUIDs.
Resolve external foreign-key references before replacing the primary key.
Rehearse the copy on a target clone. Resolve lookup-key collisions under `utf8mb4_tr_0900_ai_ci` before cutover.
Confirm capacity for both tables, the required lock interval, and a tested backup.
Stop all Example writers and old application processes before applying the conversion.
The migration requires MySQL `LOCK TABLES` authority for its copy and atomic rename.

## Migration phases

`Version20261014030000` creates `example_table_uuid` with the final mapping.
The migration holds write locks through copy and atomic rename, then releases them.
The copy preserves UUID bytes, selectors, values, JSON details, active state, deletion state, and timestamps.
Database primary and active-identity constraints reject duplicate keys during the copy.
The migration atomically renames the original to `example_table_legacy_20261014` and activates the copy.
The legacy table keeps its original integer-to-public-UUID map for forward recovery.
New timestamps use `DATETIME(6)`. Existing second precision is preserved without invented microseconds.

Require equal row counts and a full UUID and content comparison before starting the new application.
Require no ORM schema differences and verify actual timestamp precision through `information_schema.columns`.
Test reads, updates, soft deletion, replacement after deletion, and generated UUID round trips.
Entries with equal creation times use their binary UUID as the pagination tie-breaker.

## Interrupted conversion and recovery

MySQL DDL is not transactional. Inspect the original, shadow, and legacy table names after any failure.
A failed copy leaves the original table available. Do not remove or rename it automatically.
Correct the cause and reconcile the completed migration phases before another attempt.
An interrupted version record after cutover requires schema and migration-record reconciliation.
Do not rerun the complete migration against the activated binary schema.

The release manifest marks previous-application rollback unsafe.
Use a reviewed forward migration and the retained map for any required data recovery.
Keep the legacy table read-only to application code.
Remove it only after backup, reference, and recovery obligations have been reviewed and authorized.
