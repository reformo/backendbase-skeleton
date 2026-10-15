# ADR Implementation Status

This register records implementation coverage for every Architecture Decision Record (ADR).
Source policy and migration artifacts do not establish production activation.
Starting source revision: `ddc9946`.

| ADR | State | Implementation and evidence |
| --- | --- | --- |
| [ADR-0001](0001-modular-architecture-with-bounded-contexts.md) | Implemented; verification below | Context-owned services and the architecture suite verify modular ownership. |
| [ADR-0002](0002-ports-and-adapters-with-inward-dependencies.md) | Implemented; verification below | Ports, adapters, composition checks, and vendor construction checks enforce inward dependencies. |
| [ADR-0003](0003-shared-contracts-and-value-ownership.md) | Implemented; verification below | Shared contract and domain value ownership checks remain active. |
| [ADR-0004](0004-synchronous-command-query-separation.md) | Implemented; verification below | Void command handlers publish request-local outcomes; query handlers return immutable projections. |
| [ADR-0005](0005-php-and-lightweight-http-stack.md) | Implemented; verification below | PHP 8.5, Slim, PHP-DI, Composer platform checks, and application composition remain active. |
| [ADR-0006](0006-context-owned-composition-and-handler-registries.md) | Implemented; verification below | IdentityAndAccess owns its bindings and handler registry; consumer routes own HTTP policy. |
| [ADR-0007](0007-production-operating-model.md) | Implemented; verification below | Linux systemd units supervise consumers, relay batches, and retention cleanup. Host activation remains an operational step. |
| [ADR-0008](0008-database-ownership-between-bounded-contexts.md) | Implemented; verification below | The table ownership inventory names context and infrastructure writers, including operator evidence. |
| [ADR-0009](0009-separate-write-persistence-and-read-projections.md) | Implemented; verification below | Doctrine write records and DBAL read projections remain separate. Historical actor projections retain stored fields. |
| [ADR-0010](0010-typed-runtime-configuration.md) | Implemented; verification below | Typed configuration validates identity keys, replay destinations, connection settings, and runtime bounds. |
| [ADR-0011](0011-secret-storage-and-rotation.md) | Partial; provider input pending | Controlled runtime injection and credential rotation are implemented. Provider-specific retrieval, authentication, and renewal need provider selection. |
| [ADR-0012](0012-immutable-releases-and-schema-compatible-rollback.md) | Implemented; verification below | Immutable release scripts remain active. The new manifest marks integer-key application rollback unsafe. |
| [ADR-0013](0013-dependency-trust-and-supply-chain-evidence.md) | Implemented; verification below | The lockfile, isolated dependency install, SBOM, content digests, and current dependency audit are verified. |
| [ADR-0014](0014-test-architecture-and-quality-gates.md) | Implemented; verification below | Quality and release jobs use coverage and isolated MySQL 8.4 and Redis 8 tests. Local quality results are recorded below. |
| [ADR-0015](0015-mysql-migration-lifecycle-and-baseline-adoption.md) | Implemented; verification below | Released creation history is retained. New migration drafts define forward recovery and adoption gates. |
| [ADR-0016](0016-injectable-application-time-in-utc.md) | Implemented; verification below | Injected clocks remain authoritative. The immutable Doctrine datetime type reads and writes UTC microseconds. |
| [ADR-0017](0017-liveness-and-dependency-readiness.md) | Implemented; verification below | Identity APIs expose liveness and dependency readiness contracts. No business operation runs through a health probe. |
| [ADR-0018](0018-production-observability.md) | Implemented signals; workload evidence pending | HTTP duration and outcome logs and MySQL index, buffer, and lock diagnostics use safe fields. The identity acceptance procedure defines reconciliation queries and measurement gates. Production collection and representative workload evidence remain operational work. |
| [ADR-0019](0019-domain-events-and-integration-events.md) | Implemented; verification below | Domain listeners and integration event subscribers remain separate. Wire event names and versions stay compatible. |
| [ADR-0020](0020-atomic-business-changes-local-subscribers-and-outbox.md) | Implemented; verification below | Shared ORM transactions flush before database commit and clear failed units of work. |
| [ADR-0021](0021-message-delivery-and-inbox-deduplication.md) | Implemented; verification below | Inbox claims and completion use original message and consumer identities. An ORM flush failure rolls back and stops the consumer when its persistence session closes. A fresh unit processes the next message. |
| [ADR-0022](0022-published-event-compatibility-and-versioning.md) | Implemented; verification below | Published event payload schemas, names, and versions remain unchanged and covered by contract tests. |
| [ADR-0023](0023-rabbitmq-and-sqs-transport-support.md) | Implemented; verification below | RabbitMQ and SQS preserve original occurrence metadata. SQS consumers reject destination mismatch. |
| [ADR-0024](0024-relay-retry-and-terminal-failure-policy.md) | Implemented; verification below | Publication and consumer retry policies remain separate. Original committed publication intent remains recoverable. |
| [ADR-0025](0025-replay-and-deduplication-retention.md) | Implemented; verification below | Replay uses the original UTC occurrence and a seven-day inclusive age bound. Cleanup enforces at least 30 days and protects pending recovery evidence. |
| [ADR-0026](0026-external-effect-idempotency-and-unknown-outcomes.md) | Implemented; verification below | Manual external-effect resolution records provider evidence. Preview is default; only recorded nonacceptance permits another attempt. |
| [ADR-0027](0027-domain-aggregates-and-persistence-records.md) | Implemented; verification below | Identity aggregates own state and invariants. Doctrine records translate persistence state without becoming business aggregates. |
| [ADR-0028](0028-consumer-api-isolation-and-selection.md) | Implemented; verification below | Fixed source and API path maps isolate Example, USER, and ADMIN consumers. Browser preflight uses only whitelisted API paths. |
| [ADR-0029](0029-security-responsibility-after-identity-and-access-removal.md) | Superseded | Superseded by ADR-0037. Example retains API-key protection; Greeting HTTP endpoints remain absent. |
| [ADR-0030](0030-public-contract-authority-and-executable-examples.md) | Implemented; verification below | Each identity API has source and generated OpenAPI plus Bruno examples. Route, security, and example parity checks compare executable operations. |
| [ADR-0031](0031-stable-public-error-contracts.md) | Implemented; verification below | Stable problem codes map invalid input, authentication failure, conflicts, denied authority, and unavailable dependencies. |
| [ADR-0032](0032-boundary-validation-and-object-mapping-policy.md) | Implemented; verification below | Strict Valinor mapping and bounded JSON input reject unknown fields and coercion. Example v1 retains its documented normalization exception. |
| [ADR-0033](0033-s3-compatible-object-storage-contract.md) | Implemented; verification below | S3 operations have finite timeouts, bounded retries, exact private POST fields, and preserved download MIME types. |
| [ADR-0034](0034-notification-providers-and-partial-delivery-results.md) | Implemented; verification below | Ownership notification uses the delivery result and a stable inbox identity. Duplicate and uncertain acceptance tests prevent automatic resend. |
| [ADR-0035](0035-local-translation-catalogs-and-controlled-synchronization.md) | Implemented; verification below | The English catalog contains English messages and matching placeholders. No remote translation synchronization was performed. |
| [ADR-0036](0036-single-identity-and-access-context-and-global-accounts.md) | Implemented; verification below | Global Accounts own contact and credentials. Each Account has at most one User and one Admin, including historical actor rows. |
| [ADR-0037](0037-user-and-system-admin-jwt-realms.md) | Implemented; verification below | USER and ADMIN JWTs use separate issuers, audiences, signing keys, and route-selected realm validation. |
| [ADR-0038](0038-redis-token-allowlist-and-session-lifecycle.md) | Implemented; verification below | Redis 8 atomically records token and session allowlists. Expiry, revocation, Account blocking, and outage behavior are tested. |
| [ADR-0039](0039-uuidv7-identifiers-and-binary-storage.md) | Implemented for fresh installations | Identity and Example use assigned natural binary UUIDs. New aggregate IDs use UUIDv7. An existing Example database needs a separately reviewed conversion that preserves public UUIDs. |
| [ADR-0040](0040-assigned-uuid-identifiers-in-doctrine.md) | Implemented; verification below | Ramsey uuid_binary mappings use assigned identifiers. Real MySQL tests verify legacy and new UUID round trips. |
| [ADR-0041](0041-mysql84-character-set-and-turkish-collation.md) | Implemented for fresh installations | Docker MySQL server defaults and new business tables use utf8mb4 and Turkish UCA 9.0 collation. Native tests verify case, accent, Unicode composition, and NO PAD uniqueness. Existing databases need reviewed conversion. Identity keys retain explicit ASCII binary rules. |
| [ADR-0042](0042-logical-references-without-physical-foreign-keys.md) | Implemented; verification below | Identity relationships use scalar UUID references and writer checks. No new physical foreign keys or cascades are created. |
| [ADR-0043](0043-optimistic-concurrency-and-selective-locking.md) | Implemented; verification below | Optimistic versions, Account-first locks, fresh retry units, and ORM flush boundaries are tested. Locked actor authorization reloads reject concurrent removal. Repeated Account locks preserve pending credential changes. |
| [ADR-0044](0044-soft-deletion-active-uniqueness-and-historical-reporting.md) | Implemented; verification below | Active keys can be reused after deletion. Restoration conflicts roll back, and historical actor records remain visible to authorized queries. |
| [ADR-0045](0045-staged-identifier-and-collation-migration.md) | Fresh installation implemented; existing-target conversion pending | Consolidated migrations create final tables. The former conversion migration is removed. Existing databases need a target-specific forward conversion, recovery rehearsal, and migration-record adoption plan. |
| [ADR-0046](0046-authentication-challenges-and-mfa-gated-sessions.md) | Superseded by ADR-0049 | Ownership and actor approval remain separate. TOTP enrollment, login challenges, and factor recovery are removed. |
| [ADR-0047](0047-generated-uuid-projection-mapping.md) | Implemented; verification below | Supported read-only generated projections round-trip on MySQL 8.4 and produce no repeated ORM schema differences. |
| [ADR-0048](0048-business-key-normalization-and-uniqueness.md) | Implemented; verification below | Required Account email and E.164 mobile replace actor usernames. Email uses lowercase ASCII normalization. Database constraints enforce global active contact uniqueness and lifetime actor cardinality. |
| [ADR-0049](0049-password-authentication-without-totp.md) | Implemented | Password login issues realm sessions after ownership, Account state, and actor approval checks. |
| [ADR-0050](0050-contact-verification-before-user-registration.md) | Implemented; local verification below | Both User contacts require fresh evidence. Admin verification remains optional and preserves authorization. |

## Verification

### RedisJSON session storage on 15 October 2026

Starting source revision: `4346d39`.

- The full suite passes 878 tests and 11,414 assertions with no skipped cases.
- Executable-line coverage is 5,576 of 5,576 lines (100%). Method coverage is 1,335 of 1,335 methods (100%).
- `RedisTokenRegistry` uses the existing `RedisJsonInterface` library for atomic Lua activation and validation.
- New token and session records are JSON objects. JWT claims, key names, Account block markers, and revocation meanings remain unchanged.
- Each `JSON.SET` is followed by `EXPIRE`. The same script applies `EXPIREAT` to retain the JWT's exact expiration deadline.
- Activation rejects expired deadlines and conflicts. Command failures remove the new pair before returning an authentication-unavailable error.
- Existing string records remain readable until their original expiry. Reads do not rewrite records or renew TTL.
- Native tests verify JSON fields, automatic key removal, conflicts, expiry failure cleanup, and string-record compatibility.
- Eight Sentinel cases preserve JSON state, revocation checks, and expiry through promotion and verify stale-replica recovery boundaries.
- PHPStan level 8, coding style, complexity, coverage, documentation links, and isolated deployment checks pass.
- Production RedisJSON and readiness share the lazy native connection, Redis 8 version check, timeout, credentials, and TLS context.
- Older application versions require controlled logout before rollback because they cannot read RedisJSON records. See [session recovery](../platform/operations/identity-session-recovery.md).

### Redis Sentinel failover on 15 October 2026

Starting source revision: `de4e775`.

- The full suite passes 863 tests and 11,139 assertions with no skipped cases.
- Executable-line coverage is 5,578 of 5,578 lines (100%). Method coverage is 1,335 of 1,335 methods (100%).
- Eight native cases exercise planned promotion, primary failure, lost revocations, and lost Sentinel majority in USER and ADMIN.
- The runner recreates a primary, replica, and three Sentinels on a separate project network with random loopback ports and temporary storage.
- Application tests resolve both Redis client factories from production dependency definitions and validate real JWTs and Redis Lua state.
- A native regression exposed forced TLS negotiation on plain endpoints. Both client factories now use the typed conditional TLS context.
- TLS configuration tests preserve peer verification. The failover topology itself uses plain connections.
- Lost revocation tests demonstrate acceptance of stale sessions before controlled invalidation. Account block markers survive invalidation, and fresh login works afterward.
- The application still requires a stable primary endpoint or an endpoint update and new connection after promotion.
- Quality and release workflows run the full suite through the isolated failover runner.
- PHPStan level 8, coding style, complexity, coverage, and isolated deployment checks pass.
- No development or production service was changed. Provider-specific durability and recovery objectives still need deployment acceptance.

### Architecture reconciliation on 15 October 2026

Starting source revision: `dac1ab0`.

- The full suite passes 854 tests and 11,056 assertions with no skipped service tests.
- Executable-line coverage remains 5,577 of 5,577 lines (100%). Method coverage remains 1,334 of 1,334 methods (100%).
- Seventeen added cases cover the JWT wire contract, native MySQL collation and deadlock behavior, restore conflicts, relationship races, and Redis recovery.
- Native checks use disposable MySQL 8.4.11 and Redis 8.10.2 containers without project data mounts.
- Separate PHP processes observe real InnoDB lock waits. The deadlock test verifies error 1213, full rollback, and a fresh retry EntityManager.
- Fresh image database creation inherits utf8mb4 and utf8mb4_tr_0900_ai_ci from the configured server flags.
- Recovery-schema verification excludes the DBAL-owned operator evidence table from ORM comparison.
- PHPStan level 8, coding style, and cyclomatic complexity checks pass.
- The maintained architecture reference preserves the existing JWT contract and records fresh-installation and identifier exceptions.
- No existing project database was converted. Production failover, representative latency, and deployment acceptance remain target-specific work.

### Previous deterministic coverage verification

Local verification on 15 October 2026 covers the 26 previously uncovered lines from starting revision `a36e691`.

- PHPUnit discovered 837 tests. 825 tests passed, and 12 service tests were skipped. All 10,769 assertions passed.
- Executable-line coverage is 5,577 of 5,577 lines (100%). Method coverage is 1,334 of 1,334 methods (100%).
- `composer test` passed its coverage gate and isolated deployment-script tests.
- PHPStan level 8, coding style, and cyclomatic complexity checks passed.
- New tests cover Redis session validation and revocation, connection reuse, retry limits, rollback, manager disposal, stale revisions, and projection validation.
- Lock tests use production Doctrine metadata and the MySQL platform with connection doubles to verify generated SQL.
- No production behavior changed. No database or deployment operation ran against shared services.
- Live MySQL and Redis verification requires a prepared target. Those service tests were not run for this change.

### Earlier implementation verification

The local suite passes 762 tests with 2,969 assertions. Executable-line coverage is 5,749 of 5,749 lines (100%).
PHPStan level 8, style, and cyclomatic complexity checks pass.
A fresh MySQL database passes migration status, dry-run, application, ORM validation, and an empty mapping diff.
OpenAPI validation, route and Bruno parity, dependency audit, package content checks, and deployment-script checks pass.
One independent code review identified three required defects: stale actor authorization, closed ORM consumer sessions, and diagnostic payload exposure.
All three defects are corrected. Regression tests cover concurrent Admin removal, actual ORM flush failure and recovery, transport restart outcomes, and private event data.
The corrected code passes the listed checks. No second independent review was run.
The suite includes domain, HTTP, architecture, messaging, native MySQL 8.4, and native Redis 8 checks.
The MySQL tests create and remove only unique test databases. Redis tests use unique key namespaces.
All service checks use dedicated temporary containers without project data volumes.

## Remaining deployment decisions and operations

- ADR-0011 needs the managed secret provider before its retrieval and renewal integration can be completed.
- Collect representative p95/p99 latency, reconciliation, schema drift, and revocation-delay evidence before production acceptance under ADR-0018.
- Complete the [identity acceptance procedure](../platform/operations/identity-acceptance.md) on the selected staging topology.
- Follow [session recovery](../platform/operations/identity-session-recovery.md) before reopening traffic after uncertain Redis revocation durability.
- Install and activate systemd units only on an authorized Linux target after setting host paths and runtime injection.
- Apply migration drafts only to a named, authorized target after adoption, backup, and recovery checks.
- Reconcile original destinations and occurrence timestamps for legacy message rows before publication or replay.
- Resolve target-specific collation collisions and logical references before Example cutover.
- Keep each required code proof key through pending code deadlines and confirmed evidence recovery windows of 12 hours.

See [managed server operations](../platform/operations/managed-servers.md),
[identity operator procedures](../platform/operations/identity-operator.md), and
[Example conversion](../platform/operations/example-uuid-conversion.md).


### Contact verification on 15 October 2026

Starting source revision: `89093951dadd450051a438d4a193056f438f9eef`.

- User registration requires matching email and mobile evidence verified less than 12 hours earlier.
- Optional Admin verification preserves ownership, credential versions, approval, and existing sessions.
- The local suite passes 962 tests and 21,110 assertions. It skips 50 service-backed cases without prepared targets.
- Executable-line coverage is 5,924 of 5,924 lines (100%).
- PHPStan level 8, coding standards, complexity, OpenAPI validation, documentation links, and isolated deployment tests pass.
- Prepared-target MySQL concurrency, native Redis scripts, and representative MySQL `EXPLAIN` plans remain unverified.
- The initial migration matches production metadata without a database connection. No migration was applied.


### Account names and manual Admin seeding on 15 October 2026

- Accounts persist nullable first and last names. Registration and both identity APIs expose the optional profile fields.
- The approved manual seed creates Mehmet Korkmaz with the specified email and mobile.
- The root-only command reads a password file. Repeated seeds preserve credentials and authorization state.
- Tests prove email/mobile Admin login, unique bootstrap evidence, repeat-run behavior, and complete rollback after an audit failure.
- The local suite reports 977 tests and 21,988 assertions, with 50 service-backed cases skipped.
- Executable-line coverage is 5,969 of 5,969 lines (100%). PHPStan level 8, style, complexity, API validation, and deployment fixtures pass.
- Migration SQL matches production metadata without a database connection. No migration was applied.
- The configured local MySQL target could not be reached. The live Admin seed remains unapplied.
