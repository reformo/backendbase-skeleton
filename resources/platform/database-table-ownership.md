# Database Table Ownership

Each bounded context owns its business tables. Infrastructure owns shared technical tables.
The owning adapter is the only writer. Other contexts use contracts and integration events.

Business table names do not include a bounded-context prefix. `example_table` is the sole exception.
Keep table names unique across the shared database. Record ownership here instead of encoding it in a prefix.
Infrastructure table names identify technical responsibilities and remain unchanged.

| Owner | Tables | Identity and references |
| --- | --- | --- |
| IdentityAndAccess | `account`, `user`, `admin`, `contact_verification` | Assigned UUIDv7 `BINARY(16)` identifiers; scalar binary Account and actor references |
| ExampleCatalog | `example_table` | Assigned binary UUID primary key; read-only virtual public UUID; opaque integer target selector retained |
| Infrastructure messaging | `integration_event_outbox`, `integration_event_inbox`, `integration_event_delivery_failure` | Existing technical message and claim UUID strings |
| Infrastructure recovery | `integration_event_replay`, `external_effect_resolution` | Binary UUIDv7 audit identifiers; original technical message and consumer identity |
| Infrastructure HTTP replay | `http_request_replay` | SHA-256 scoped request identity; keyed content fingerprint; encrypted response |
| Migration runtime | `doctrine_migration_versions` | Migration class identifiers |

## Identity representation inventory

IdentityAndAccess has no previous implementation at the starting source revision.
New records allocate UUIDv7 before persistence. They need no integer-to-UUID backfill.
Account references and optional contact actor references use the same natural 16-byte representation.
The virtual `uuid` display column uses `BIN_TO_UUID(id, 0)` and has no lookup index.
JWT, HTTP, and Redis use canonical UUID strings. They do not expose binary values.

Technical outbox, inbox, message, claim, and queue identifiers retain their existing string contracts.
These identifiers identify transport operations. They are not aggregate relationship keys.
Recovery audit records keep the original message and consumer strings.

ExampleCatalog uses an integer `typeTargetId` selector. The repository contains no owning entity table for that selector.
Do not invent a mapping to User or Admin identifiers.
Fresh installation creates the final binary Example schema directly.
An existing database requires a separate conversion plan that preserves its public UUIDs.
New entries allocate UUIDv7. HTTP fields and the opaque integer selector remain unchanged.
The consolidated history creates no shadow or legacy Example table.
Any retained tables from an earlier conversion remain recovery artifacts. No runtime adapter writes them.

## Writer checks

Identity writers validate Account existence, active state, actor ownership, and contact realm before persistence.
Actor Account references cannot change. No identity migration creates a physical foreign key or cascade.
Database uniqueness protects one User and one Admin per Account, including removed actor rows.
Email and required mobile belong to the Account table. Each contact has a generated active-key unique index.
User and Admin tables contain no username columns or login-name indexes.

Account locks precede actor locks. Authentication counters use an exclusive Account lock.
Actor mutations protect observer and target Accounts in canonical UUID order.
Account-bound contacts receive their exclusive lock after Account and actor protection.
The Account owns nullable first and last names. The manual initial Admin seeder writes its Account and Admin in one transaction.
Admin state retains invitation and approval evidence. A database-scoped lock serializes operator setup and manual seeding.
The seeder creates no User or contact evidence.

Pre-registration contacts have no identity owner. Registration locks email, then mobile, before reading the authorization time.
The registration policy selects required contact proofs. Matching fresh optional proofs are consumed when present.
Account state keeps `registrationAccepted` separate from ownership. Legacy state derives acceptance from its existing ownership value.
Post-login User email verification binds the Account and User actor under the separate `user-contact-verification` purpose.
Historical projections include removed actors and join their retained Account for email and mobile.
The Account primary key supports this join. Historical reads do not acquire write locks.

## Adoption gates

The four installation migrations support fresh installations only. They create all eleven application tables with final names directly.
Existing databases require separate schema and version-record adoption, including databases that ran earlier consolidated installation definitions.
See [migration adoption](operations/migration-consolidation.md) before using an existing database.
Inventory actual target tables, references, existing identifiers, collations, and migration history before deployment.
Compare known old IdentityAndAccess artifacts with this table list. Do not adopt unknown rows or sessions automatically.
Use controlled logout for previous token and session formats. New realm policies and allowlists accept only the current format.

Legacy outbox rows require their original destination before publication or replay.
Use authoritative broker and inbox evidence. Preserve original occurrence time, consumer, and message identity.
Reject ordinary recovery older than seven days. Use a separate reviewed procedure for older projection rebuilding.

Validate zero duplicate keys and orphan references on an isolated target clone before cutover.
Preserve recovery evidence after soft deletion and forward schema changes.
Apply migration drafts only to a named, authorized database target.
