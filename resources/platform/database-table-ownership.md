# Database Table Ownership

Each bounded context owns its business tables. Infrastructure owns shared technical tables.
The owning adapter is the only writer. Other contexts use contracts and integration events.

| Owner | Tables | Identity and references |
| --- | --- | --- |
| IdentityAndAccess | `identity_account`, `identity_user`, `identity_admin`, `identity_challenge`, `identity_operator_action` | Assigned UUIDv7 `BINARY(16)` identifiers; scalar binary Account and actor references |
| ExampleCatalog | `example_table` | Assigned binary UUID primary key; read-only virtual public UUID; opaque integer target selector retained |
| Infrastructure messaging | `integration_event_outbox`, `integration_event_inbox`, `integration_event_delivery_failure` | Existing technical message and claim UUID strings |
| Infrastructure recovery | `integration_event_replay`, `external_effect_resolution` | Binary UUIDv7 audit identifiers; original technical message and consumer identity |
| Migration runtime | `doctrine_migration_versions` | Migration class identifiers |

## Identity representation inventory

IdentityAndAccess has no previous implementation at the starting source revision.
New records allocate UUIDv7 before persistence. They need no integer-to-UUID backfill.
Account references and challenge actor references use the same natural 16-byte representation.
The virtual `uuid` display column uses `BIN_TO_UUID(id, 0)` and has no lookup index.
JWT, HTTP, and Redis use canonical UUID strings. They do not expose binary values.

Technical outbox, inbox, message, claim, and queue identifiers retain their existing string contracts.
These identifiers identify transport operations. They are not aggregate relationship keys.
Recovery audit records keep the original message and consumer strings.

ExampleCatalog uses an integer `typeTargetId` selector. The repository contains no owning entity table for that selector.
Do not invent a mapping to User or Admin identifiers.
The staged Example conversion reuses each existing public UUID through `UUID_TO_BIN(uuid, 0)`.
New entries allocate UUIDv7. HTTP fields and the opaque integer selector remain unchanged.
The retained `example_table_legacy_20261014` table is a recovery artifact. No runtime adapter writes it.

## Writer checks

Identity writers validate Account existence, active state, actor ownership, and challenge realm before persistence.
Actor Account references cannot change. No identity migration creates a physical foreign key or cascade.
Database uniqueness protects one User and one Admin per Account, including removed actor rows.
Active login uniqueness belongs to each actor table. Account email uniqueness belongs to the Account table.

Account locks precede actor locks. Authentication counters use an exclusive Account lock.
Actor mutations protect observer and target Accounts in canonical UUID order.
Challenges receive their exclusive lock after Account and actor protection.
Historical projections use stored fields without write locks or joins to live Account attributes.

## Adoption gates

Inventory actual target tables, references, existing identifiers, collations, and migration history before deployment.
Compare known old IdentityAndAccess artifacts with this table list. Do not adopt unknown rows or sessions automatically.
Use controlled logout for previous token and session formats. New realm policies and allowlists accept only the current format.

Legacy outbox rows require their original destination before publication or replay.
Use authoritative broker and inbox evidence. Preserve original occurrence time, consumer, and message identity.
Reject ordinary recovery older than seven days. Use a separate reviewed procedure for older projection rebuilding.

Validate zero duplicate keys and orphan references on an isolated target clone before cutover.
Preserve recovery evidence after soft deletion and forward schema changes.
Apply migration drafts only to a named, authorized database target.
