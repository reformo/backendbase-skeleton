# Decision Source: Identity and Access Architecture Report

- **Source date:** 2026-10-15.
- **Revision:** 09.
- **Original document:** `identity_access_uuidv7_doctrine_mysql84_utf8mb4_tr0900_tenantsiz_mfa_haric_2026-10-15.html`.
- **SHA-256:** `fc4d4fdcc0991313e87a44ebc7eea9d3933b882842cb452952867727a1ffa1df`.

This reference reconciles the source report with the current implementation. It provides a repository-local reference for the ADRs.

The report contains conceptual code, SQL, routes, and an action plan. It states that the author did not inspect the repository or execute migrations. Accepted ADRs record approved target decisions. They do not establish that those decisions are implemented.

Authentication follows [ADR-0049](../0049-password-authentication-without-totp.md). Password login requires contact ownership, an active Account, and actor approval.

## Accepted Decisions

### Context and Actor Scope

Source sections: `overview`, `decisions`, and `domain`.

- Use one IdentityAndAccess bounded context for Account, credentials, authentication challenges, sessions, token issuance, and access policy.
- Use global Account identity with separate User and System Admin aggregates for actor business behavior and lifecycle.
- Keep the scope to User and System Admin. The report defines no tenant model or additional actor types.
- Separate login flows do not require separate identity contexts.
- Preserve the existing project directories and framework conventions.
- Keep domain rules in Domain and orchestration in Application.

Records: [ADR-0036](../0036-single-identity-and-access-context-and-global-accounts.md) and [ADR-0049](../0049-password-authentication-without-totp.md).

### Realm Authentication and Sessions

Source sections: `onboarding`, `jwt`, `redis`, and `middleware`.

- Separate USER and ADMIN authentication realms by issuer, audience, signing key, and expected actor type.
- Select realm policy from trusted route configuration before validating the token.
- Validate signature, permitted algorithm, issuer, audience, expiration, not-before time, and actor type.
- Keep account and actor identifiers as UUID strings in JWT and JSON.
- Require an active access-token record identified by `jti` and an active session identified by `sid`.
- Match token, session, account, actor, and realm identities before constructing a verified principal.
- Store access-token and session records successfully, with expiry, before returning the access token.
- Reject access when Redis validation cannot complete. Use a safe service-unavailable error contract for dependency failure.
- Use trusted current account status or version checks together with Redis revocation when an account is closed.
- MySQL and Redis do not form one atomic database transaction.
- Keep ownership verification, password authentication, and actor approval as separate gates.
- Issue the session after all required gates pass. TOTP and MFA enrollment, login completion, and recovery are absent.
- Keep permission and lifecycle decisions in Application and Domain.

The implementation preserves the following JWT contract. The report's conceptual `sub = Account ID` and snake-case actor claims do not describe this contract.

| Claim | Meaning |
| --- | --- |
| `sub` | Actor UUID string |
| `accountId` | Global Account UUID string |
| `actorType` | `User` for USER; `SystemAdmin` for ADMIN |
| `realm` | `USER` or `ADMIN` |
| `authVersion` | Current Account authentication version, as an integer |
| `jti`, `sid` | Canonical UUID strings for the access token and login session |
| `iss`, `aud`, `iat`, `nbf`, `exp` | Issuer, audience, and validity times |

The JWT header contains `alg: HS256`, `typ: JWT`, and a configured `kid`.
The default lifetime is 900 seconds. Configuration permits 60 through 3600 seconds.
The APIs provide no refresh-token flow.

UserApi uses `/user-api` and `X-Source-Id: user`.
SystemAdminApi uses `/system-admin-api` and `X-Source-Id: system-admin`.
The fixed consumer map and trusted route policy select the expected realm before token validation.
Both APIs expose `POST /auth/login` and return `session` with `accessToken`, `tokenType`, and `expiresAt`.

Redis keys use `identity:{REALM:accountId}:jti:tokenId`, `identity:{REALM:accountId}:sid:sessionId`, and `identity:{REALM:accountId}:blocked`.
The braces keep each Account's realm records in one Redis Cluster hash slot.
Matching Redis records and current MySQL Account and actor state are required on each authenticated request.
See [session recovery](../../platform/operations/identity-session-recovery.md) for restore and failover restrictions.

Records: [ADR-0037](../0037-user-and-system-admin-jwt-realms.md), [ADR-0038](../0038-redis-token-allowlist-and-session-lifecycle.md), and [ADR-0049](../0049-password-authentication-without-totp.md).

### Entity Identifier Representation

Source sections: `uuid`, `index-economics`, and `doctrine`.

- Generate entity and aggregate identifiers with `ramsey/uuid` UUIDv7 before persistence.
- Store primary identifiers and logical references as `BINARY(16)`.
- Use assigned identifiers and the Doctrine `uuid_binary` type from `ramsey/uuid-doctrine`.
- Use natural UUID byte order. Do not use UUIDv1 ordered-time swapping.
- Use `BIN_TO_UUID(id, 0)` and `UUID_TO_BIN(:uuid, 0)` when SQL conversion is needed.
- Expose a readable `uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin` virtual generated projection.
- Keep the readable projection out of primary keys, relationship references, and normal lookup indexes.
- Use binary columns for lookups and joins.
- Keep Doctrine and Ramsey conversion inside adapters. Domain identifiers can use project-owned value objects.
- Use explicit creation and business dates for reports. UUIDv7 generation time does not reconstruct an old record's creation time.
- Measure actual index size and query latency. The report's index-size arithmetic is illustrative.

Identity records already use binary UUIDv7 identifiers. They require no integer backfill in a fresh installation.
Existing Example public UUIDs retain their original identity during a reviewed conversion. New Example identifiers use UUIDv7.
Technical messaging identifiers retain their string contracts. Example `typeTargetId` remains an opaque integer selector.
See the [identifier inventory](../../platform/database-table-ownership.md) for the exact scope.

Records: [ADR-0039](../0039-uuidv7-identifiers-and-binary-storage.md), [ADR-0040](../0040-assigned-uuid-identifiers-in-doctrine.md), and [ADR-0047](../0047-generated-uuid-projection-mapping.md).

### Database Text Standard

Source sections: `charset`, `schema`, and `open`.

- Use MySQL 8.4 and InnoDB for the target database.
- Use `utf8mb4` with `utf8mb4_tr_0900_ai_ci` for database and business-table defaults.
- Configure the DBAL connection charset as `utf8mb4`.
- Keep binary identifiers independent of text collation.
- Override the readable UUID projection with `ascii` and `ascii_bin`.
- Normalize identity login names and Account emails to lowercase ASCII in Domain. Reject unsupported Unicode identity keys.
- Use explicit `ascii_bin` identity key columns. Active logins are unique within each realm; active Account email is global.
- Permit at most one User and one Admin per Account, including removed actor rows.
- Test Turkish I variants, accents, Unicode normalization, and trailing-space behavior against business uniqueness rules.
- Analyze existing duplicates, index changes, locks, and generated-column overrides before collation conversion.

Docker Compose configures explicit MySQL server defaults for fresh database creation.
Changing server defaults does not convert existing databases or columns.
See [database collation procedures](../../platform/operations/database-collation.md).

Records: [ADR-0041](../0041-mysql84-character-set-and-turkish-collation.md) and [ADR-0048](../0048-business-key-normalization-and-uniqueness.md).

### References, Transactions, and Lifecycle

Source sections: `schema`, `transactions`, `lifecycle`, and `history`.

- Do not create physical foreign-key constraints.
- Use indexed binary logical references and explicit existence, access, and lifecycle validation.
- Retain primary keys, applicable uniqueness constraints, nullability constraints, and indexes.
- Prevent ORM associations, cascades, or schema generation from adding unapproved foreign keys or lifecycle effects.
- Commit writes that must change together in one MySQL transaction.
- Use optimistic integer state versions for ordinary aggregate updates.
- Use a common selective locking protocol for reference creation and lifecycle changes.
- Use exclusive locking or atomic conditional updates for shared capacity and counter invariants.
- Fully roll back before bounded retry after eligible deadlocks or lock timeouts.
- Restart a failed ORM attempt with a clean EntityManager and UnitOfWork.
- Preserve the current isolation level unless a command-specific change is reviewed and tested.
- Keep reporting read-only and use an appropriate consistent snapshot without unnecessary row locks.
- Use soft deletion for visibility separately from domain lifecycle state.
- Include deleted records in historical reports using their stored values.
- Do not require event sourcing or past-state reconstruction for this reporting requirement.
- Apply active-key uniqueness only where the domain requires it.
- Validate lifecycle and key conflicts during restoration.

Records: [ADR-0042](../0042-logical-references-without-physical-foreign-keys.md), [ADR-0043](../0043-optimistic-concurrency-and-selective-locking.md), and [ADR-0044](../0044-soft-deletion-active-uniqueness-and-historical-reporting.md).

### Migration, Measurement, and Verification

Source sections: `migration`, `risk`, `plan`, and `tests`.

- Inventory target identifiers, references, contracts, sessions, caches, jobs, collations, and migration history before drafting conversion.
- Where integer identifiers exist, use staged UUID allocation, a persistent identifier map, backfill, reference conversion, cutover, and later cleanup.
- Verify complete backfill and zero duplicate or orphan logical references.
- Assess old JWT and Redis identifier compatibility before cutover.
- Review generated-column schema differences and collation uniqueness changes.
- Test UUID persistence and hydration, natural byte order, virtual projection, and schema-diff preservation.
- Test cross-realm rejection, token/session mismatch, revocation, and Redis failure.
- Test soft deletion, controlled restoration conflicts, optimistic conflicts, and locking races.
- Use real MySQL 8.4 and Redis 8 for affected integration behavior. Use separate database connections for concurrency tests.
- Memory and SQLite tests do not prove InnoDB locking behavior.
- Measure point-lookup and command latency, reference joins, index size, and buffer use on representative data.
- Monitor lock waits, deadlocks, reconciliation, schema drift, revocation delay, and security rejection rates.
- Keep raw JWTs, private keys, and sensitive payloads out of diagnostics.
- Record recovery evidence before authorized target migration.

Records: [ADR-0014](../0014-test-architecture-and-quality-gates.md), [ADR-0015](../0015-mysql-migration-lifecycle-and-baseline-adoption.md), [ADR-0018](../0018-production-observability.md), and [ADR-0045](../0045-staged-identifier-and-collation-migration.md).

## Selected Implementations and Remaining Verification

Source sections: `onboarding`, `doctrine`, and `open`.

- Virtual UUID projections use read-only Doctrine mapping with schema-parity tests.
- Identity normalization and active uniqueness follow the ASCII policy above.
- Integer conversion applies only to targets identified by inventory. Consolidated migrations support fresh installations.
- Existing databases require a separate reviewed conversion and migration-record adoption plan. No automatic upgrade command is supplied.
- Legacy sessions require controlled logout before activation of the current contract.
- Confirm deployed database and Redis versions, data volume, migration recovery limits, and generated-column schema behavior.
- Admin enrollment requires separate invitation and approval evidence. Local root operator procedures establish the first Admin.
- Eligible deadlocks and lock timeouts permit at most three complete attempts with fresh EntityManagers.
- Account locks precede actor locks. Cross-Account mutation locks use canonical UUID order. Current isolation remains unchanged.
- Select observability tools, signal retention, alert thresholds, and incident ownership.

Native regression tests cover MySQL deadlocks, Turkish collation, active-key restore conflicts, and challenge creation versus User removal.
Redis recovery tests cover missing and partial allowlists, reconnect behavior, and stale records after Account closure.
These tests do not establish a production replica topology, failover durability, or representative performance.
Use the [acceptance procedure](../../platform/operations/identity-acceptance.md) for those deployment gates.

The report does not select a hosting model, global database separation policy, secret-store provider, replay window, provider-effect reconciliation policy, or general mapper strictness.

Those policy choices remain in [ADR-0007](../0007-production-operating-model.md), [ADR-0008](../0008-database-ownership-between-bounded-contexts.md), [ADR-0011](../0011-secret-storage-and-rotation.md), [ADR-0025](../0025-replay-and-deduplication-retention.md), [ADR-0026](../0026-external-effect-idempotency-and-unknown-outcomes.md), and [ADR-0032](../0032-boundary-validation-and-object-mapping-policy.md).

## Repository Comparison

- [ADR-0029](../0029-security-responsibility-after-identity-and-access-removal.md) records the previous API-key-only identity boundary. [ADR-0037](../0037-user-and-system-admin-jwt-realms.md) supersedes that decision.
- Consumer API separation remains valid. [ADR-0028](../0028-consumer-api-isolation-and-selection.md) adds trusted route-based realm policy.
- The report leaves physical database ownership open. [ADR-0008](../0008-database-ownership-between-bounded-contexts.md) now accepts a shared database with exclusive context table ownership.
- [ADR-0027](../0027-domain-aggregates-and-persistence-records.md) retains domain and persistence separation with explicit identifier mapping and logical references.
- Architecture, CQRS, event, outbox, release, and external-capability decisions remain valid where the report does not replace them.
- The current repository already includes Ramsey UUID packages, UUIDv7 generation, and `uuid_binary` registration.
- The current lock file records Doctrine ORM 3.7.5, DBAL 4.5.0, Ramsey UUID 4.9.4, and Ramsey UUID Doctrine 2.1.0.
- The report's assumption that existing identifiers are integers is not a repository-wide finding. Confirm every target schema before selecting migration steps.
- IdentityAndAccess implements global Accounts, separate actor aggregates, password authentication, Redis allowlists, and current-state checks.
- Fresh migrations create the final binary schema. Existing databases require target-specific adoption work under ADR-0045.
- Source artifacts and passing tests do not establish production deployment or migration completion.
