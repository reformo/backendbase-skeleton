# Persistence

The domain does not know Doctrine. Context-owned ports separate behavior from storage.

## Write side

- Write handlers depend on a write repository port.
- Doctrine ORM records map typed aggregate snapshots to columns.
- Derive each mapped Doctrine entity class name from its full table name in PascalCase.
- Preserve table prefixes and words: `example_table` maps to `ExampleTable`, and `identity_account` maps to `IdentityAccount`.
- Declare all mapped fields in the entity class, including shared identifiers, versions, and timestamps.
- Doctrine entity classes must not extend another class or receive mapped fields from a trait.
- Interfaces and behavior-only traits can supply shared contracts or methods.
- Records reconstitute aggregates through domain factories.
- Adapters load and save state without business decisions.
- Use native string-backed enums with Doctrine `enumType` mapping.

## Query indexes

Review every runtime SQL statement, query-builder predicate, and Doctrine lookup against current migration and ORM indexes.
A required index is part of the query implementation. Add its metadata, forward migration, and regression checks in the same change.
Reuse existing primary, unique, and composite index prefixes before adding an index.
Put equality filters before grouping and ordering columns when that supports the query.
Range predicates can limit later key-part use. Do not assume one index satisfies independent ranges and ordering.
Document intentional full scans for aggregate reports, system views, and migration validation.
Verify optimizer decisions with representative `EXPLAIN` plans on an identified, prepared MySQL target.
SQLite query-plan checks prove local query-pattern support; they do not prove MySQL index selection or production speed.

### Runtime query inventory

The review covers application adapters, ORM lookup criteria, readiness and diagnostics, migration validation, and the reference seeder.
Test-only SQL and vendor-generated migration metadata queries are not application index requirements.

| Query owner | Query pattern | Index coverage or required change |
| --- | --- | --- |
| `ExampleCatalog/Adapters/Persistence/Doctrine/EntryReadRepository` | Criteria lookup by type, nullable target, group, and key | Existing `example_search_idx` covers the leading equality fields. The remaining soft-removal check filters matching identities. |
| `ExampleCatalog/Adapters/Persistence/Doctrine/EntryWriteRepository` | Active record by binary ID or lookup identity | Primary key or existing `example_search_idx`; no new point-lookup index. |
| `EntryReadRepository::getEntryGroupsByType` | Fixed type, target, and `deleted_at`; distinct groups ordered by group | The installation includes `example_groups_idx`. Earlier indexes place other unconstrained columns before the removal filter. |
| `EntryReadRepository::getEntriesByGroup` | Fixed type, target, group, and `deleted_at`; order by `created_at, id` | The installation includes `example_group_entries_idx`. Earlier indexes do not supply this ordered prefix. |
| `IdentityRecordStore`, `DoctrineIdentityStore`, `IdentityActorRecords`, `DoctrineCredentialStore` | Binary IDs, active email, active mobile, or actor Account reference | Identity primary keys, active-email and active-mobile unique indexes, and lifetime actor Account unique indexes. Each lookup selects at most one identity. |
| `AdminSeeder` | Initial Account by active email or mobile; Admin by Account ID; whole-Admin count | Existing active-contact and Admin Account unique indexes. The bootstrap count intentionally scans the Admin table. |
| `DoctrineContactStore` | Binary proof ID or exact context hash, realm, purpose, and contact type | Contact primary key and `contact_info_context_slot_unq`. Bound-owner checks use existing Account and actor primary keys. |
| `DoctrineIdentityReadRepository::actorHistory` | Actor binary ID with an Account join | User or Admin primary key and Account primary key. Virtual display UUIDs do not need a lookup index. |
| `DoctrineOperatorStore` | Bootstrap key plus target; initial Admin count; audit inserts | Existing bootstrap unique index limits the target check to one row. The initial count is a deliberate aggregate. Inserts use existing primary and unique keys. |
| `DoctrineInboxMessageTransaction`, `ExternalEffectClaims`, `DoctrineExternalEffectResolver` | Consumer and message identity, with claim or state checks | Existing inbox composite primary key. Extra state or claim filters apply to that one identity. |
| `DoctrineQueueMessageFailureStore` | Consumer and message identity | Existing delivery-failure composite primary key. |
| `DoctrineOutboxMessageStore`, `DoctrineOutboxReplay` | Message ID updates or locked reads | Outbox primary key. Replay's consumer/message inbox check uses the inbox primary key. |
| `DoctrineOutboxMessageStore::claimNext` | Unpublished, available, unclaimed rows ordered by creation and ID | Existing pending index supports the equality/range filter. Creation ordering is not fully covered after independent ranges. An alternate relay index needs representative MySQL plan evidence before addition. |
| `DoctrineOutboxMonitor::status` | Aggregate over all unpublished rows | Existing pending-index prefix; the aggregate must inspect its matching set. No speculative covering index. |
| `DoctrineIntegrationMessageLogCleaner::cleanOutbox` | Published cutoff plus cross-consumer pending inbox and failure probes | Existing outbox pending-index prefix covers the cutoff. The installation includes message-first status indexes for both correlated probes. Consumer-first primary keys do not provide this leading message lookup. |
| `DoctrineIntegrationMessageLogCleaner::cleanInbox` | Processed cutoff with outbox and failure protection; identity deletes | Existing processed index, outbox primary key, and delivery-failure primary key. Inbox deletes use its composite primary key. |
| `DoctrineIntegrationMessageLogCleaner::cleanDeliveryFailures` | Terminal cutoff with inbox and outbox protection; identity deletes | Existing dead-letter index and inbox/outbox primary keys. Failure deletes use its composite primary key. |
| `DoctrineIntegrationEventOutbox`, recovery audit writers | Inserts into outbox, replay, and resolution tables | Existing primary and declared audit indexes; no new read pattern. |
| `EntrySeeder` | Existing Example identity before insert | Existing Example search-index prefix. |
| `MySQLReadinessCheck`, `MySqlDiagnostics`, migration preconditions | `SELECT 1`, server metrics, schema views, and whole-table adoption validation | Server-owned access paths or deliberate validation scans. Application indexes do not belong on system views. |

### Required indexes

| Table | Index | Ordered columns |
| --- | --- | --- |
| `example_table` | `example_groups_idx` | `type, type_target_id, deleted_at, lookup_group` |
| `example_table` | `example_group_entries_idx` | `type, type_target_id, lookup_group, deleted_at, created_at, id` |
| `integration_event_inbox` | `integration_event_inbox_message_processed_idx` | `message_id, processed_at` |
| `integration_event_delivery_failure` | `integration_event_delivery_failure_message_terminal_idx` | `message_id, dead_lettered_at` |

`Version20260823000000` and `Version20260823215059` create these four non-unique indexes during fresh installation.
Doctrine metadata declares the two Example indexes. Infrastructure migrations own the messaging indexes.
`RequiredQueryIndexesTest` verifies index column order, ORM alignment, SQLite plans for null and non-null targets, message probes, and consolidated installation SQL.
`MySqlQueryIndexesTest` checks native fresh installation and index creation on the prepared MySQL 8.4 service-test target.
The installation migrations are non-transactional. After partial failure, compare completed tables, index names, and ordered columns before an approved recovery action.
Index additions increase write and storage costs. Check representative MySQL plans and row counts before deployment.

References: [MySQL index use](https://dev.mysql.com/doc/refman/8.4/en/mysql-indexes.html), [ORDER BY optimization](https://dev.mysql.com/doc/refman/8.4/en/order-by-optimization.html).

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

`EntityFieldOwnershipTest` checks that discovered entities have no parent class and own all mapped fields.
Compare production metadata and generated schema SQL when moving field declarations. Field placement changes must preserve the schema unless a schema change is authorized.

Example group queries return `EntryGroupPage`. Both adapters count all matching distinct groups before pagination. Doctrine applies the requested limit and offset in SQL. It does not truncate the result set at 1,000 groups.

Identity Accounts store nullable `first_name` and `last_name` columns as UTF-8 text with a 160-character limit.
Names have no uniqueness or lookup index. Identity and history reads select them through the Account primary key.

Identity and Example records use assigned binary UUID identifiers, read-only virtual UUID projections, and optimistic versions.
New aggregate identifiers use UUIDv7. Fresh Example installation uses binary UUIDs directly.
An existing database needs a separate reviewed conversion and migration-history adoption plan.
`DoctrineContactStore` locks verification evidence by its binary primary key.
Its context-slot lookup uses the unique `(context_hash, realm, purpose, contact_type)` index.
Registration locks both proof identifiers in sorted order before it reads UTC time.
Bound evidence also checks current Account and actor state under Account-first locks.
No contact lookup requires a separate Account index.

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
