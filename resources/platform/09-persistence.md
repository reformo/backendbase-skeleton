# Persistence

The domain does not know Doctrine. Context-owned ports separate behavior from storage.

## Write side

- `PathFinder::doctrineEntityPaths()` includes the shared entity directory only when it exists. Context and nested module discovery remain available.
- Write handlers depend on a write repository port.
- Doctrine ORM records map typed aggregate snapshots to columns.
- Derive each mapped Doctrine entity class name from its full table name in PascalCase.
- Do not add bounded-context prefixes to business tables. Keep `example_table` as the sole exception.
- Preserve each table's words in its entity name: `example_table` maps to `ExampleTable`, and `account` maps to `Account`.
- Declare all mapped fields in the entity class, including shared identifiers, versions, and timestamps.
- Doctrine entity classes must not extend another class or receive mapped fields from a trait.
- Interfaces and behavior-only traits can supply shared contracts or methods.
- Records reconstitute aggregates through domain factories.
- Adapters load and save state without business decisions.
- Use native string-backed enums with Doctrine `enumType` mapping.

Example details use Doctrine `JSON_OBJECT` hydration to preserve nested object and array shapes. The SQL column remains JSON.
The DBAL mapper accepts legacy empty `[]` details as an empty object. Nonempty lists and integer top-level member names remain invalid.
Both Example read adapters order entry pages by `created_at` and UUID before slicing. Their shared contract tests use reversed insertion order.

## JSON column policy

For database JSON columns whose declared value is not a list, use an immutable context-owned class by default.
An explicitly documented exception can define a different representation.
This policy concerns database columns. It does not impose storage classes on HTTP JSON bodies or Redis records.

### Default mapping

- Place the class under `<BoundedContext>/Domain/Primitives` and implement `JsonSerializable`.
- Provide `fromArray()` for validated reconstruction and `toArray()` for explicit storage conversion.
- Require declared fields and exact types. Distinguish missing fields from nullable values. Do not silently coerce values or supply defaults.
- Reject unknown fields unless the documented compatibility contract requires their preservation. Never silently discard them during a read/write cycle.
- Keep fields typed and readonly. Returned objects must be immutable snapshots without mutable nested references.
- Keep Doctrine mapped fields as arrays. A typed read method reconstructs the class; a typed replacement method stores `toArray()`.
- Use the same classes and conversion contract in ORM, DBAL, seeders, and other writers. Decode and encode JSON at the persistence boundary.
- Validate selected scalar JSON projections directly. Do not fetch unused fields solely to reconstruct a complete value.
- Keep authorization and other business decisions on aggregates. Storage serialization must not become a public response or a diagnostic log.
- Preserve JSON object and list shapes, including empty `{}` and `[]`. Document an alternative mapping when array storage cannot preserve the contract.

The immutable-state exception to the two-property limit applies to these classes when they have typed readonly fields and validated reconstruction.
Adding a class alone does not authorize a schema change or data migration.
Review existing rows and mixed-version readers before changing required fields, accepted types, or unknown-field behavior.

### Lists and other root shapes

Classify each column from its declared storage contract, not from one runtime value.
A JSON list corresponds to consecutive zero-based integer indexes. A keyed map is not a list.
An empty PHP array cannot establish whether the intended JSON value is `{}` or `[]`.
List columns do not require an object wrapper for the list itself. Validate their elements and preserve their order and JSON list shape.
Element classes can still be required by their own contracts.
SQL nullability remains explicit and separate from the JSON root shape.
Scalar or mixed-root JSON needs a documented representation exception. Do not convert it to an object merely to satisfy this policy.

### Explicit exceptions

| Column | Owner and reason | Mapping and required protection |
| --- | --- | --- |
| `example_table.details` | ExampleCatalog owns open-ended object contents. Fixed state fields would restrict accepted detail keys. | Retain `EntryDetails` validation and Doctrine `JSON_OBJECT` mapping. Preserve nested objects and lists, empty shapes, and accepted depth. Retain the documented legacy empty-list read compatibility. Verify malformed data and ORM/DBAL round trips. |
| `integration_event_outbox.payload` | Shared messaging stores multiple event schemas and versions. The generic relay must remain independent of business classes. | Retain versioned producer and consumer contracts with generic payload storage and relay decoding. Preserve event names, versions, and nested JSON shapes. Verify producer, stored payload, outgoing/incoming transport, and registered consumer together. |

These exceptions do not remove validation or permit silent data loss.
Document each additional exception in the owning context and this policy, or link its authoritative contract here.
Record the exact table and column, owner, reason, representation, validation, compatibility behavior, and verification scope.

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
| `DoctrineContactStore` | Binary proof ID or exact context hash, realm, purpose, and contact type | Contact primary key and `contact_verification_context_slot_unq`. Bound-owner checks use existing Account and actor primary keys. |
| `DoctrineIdentityReadRepository::actorHistory` | Actor binary ID with an Account join | User or Admin primary key and Account primary key. Virtual display UUIDs do not need a lookup index. |
| `DoctrineOperatorStore` | First-Admin existence and sole Admin identity; database-scoped setup lock | Existing indexes cover reads limited to one or two Admin rows, including removed rows. MySQL advisory locks require no table or index. |
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
Accounts store their Argon2id password hash directly in required `password_hash VARCHAR(255)`.
The credential store reads and writes a string under the existing Account locks. Password hashes have no lookup index.
Account `state` JSON stores `ownershipVerified`, `enabled`, and `authVersion`.
Persist ownership verification as a boolean. Domain snapshots and Doctrine hydration use the same `ownershipVerified` key.

### Typed identity JSON state

Identity JSON state uses immutable classes under `IdentityAndAccess/Domain/Primitives`.
`AccountState` owns Account flags and authentication version. `ActorState` owns User and Admin approval fields.
`ContactState` represents the existing Contact snapshot, including its duplicated column values.
These classes implement `JsonSerializable`. Their `jsonSerialize()` methods delegate to `toArray()`.
`fromArray()` requires every declared field, validates its PHP type, and rejects unknown or numeric keys.
Nullable fields must be present. Missing values never receive defaults.
Business transitions and authorization decisions remain on the existing aggregates.

Each Doctrine entity keeps its mapped `state` field as an array.
`state()` reconstructs the corresponding immutable class on each call.
`replaceState()` accepts that exact class and stores its `toArray()` result.
These methods belong to persistence records. Application code continues to use aggregate operations and repository ports.
A returned state is a detached immutable snapshot. Persist a replacement explicitly; modifying an exported array does not modify the record.
The initial Admin seeder uses the same classes for encoding and repeat-run validation.
Actor history reads validate the complete actor state before building the public read model.

Public readonly fields support mapping. They do not authorize callers to move business decisions outside aggregates.
Immutable state classes are exempt from the two-property limit. They must keep typed readonly fields and explicit validated reconstruction.
Do not serialize these objects into public responses or logs. Contact state includes internal code hashes and contact data.

The JSON shapes, mapped column types, and indexes stay unchanged. This change needs no migration.
Contact hydration still takes identity, ownership references, verification time, and revision from mapped columns.
The corresponding JSON copies remain stored for compatibility and do not override those columns.
Existing rows with the declared keys and types remain readable. Unknown fields now cause an explicit failure.
Review stored state before deployment. Coordinate future key additions across all readers during mixed-version deployments.
Example details and outbox payloads use the [explicit exceptions](#explicit-exceptions). Redis records are outside this database-column policy.

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
