# Identity Acceptance Evidence

Use this procedure on an identified, authorized staging clone before production acceptance.
Record the code revision, service versions, schema revision, data volume, target, operator, and UTC test interval.
Keep measured results separate from source implementation status.

## Deterministic and native regression checks

Run the ordinary suite with service tests disabled when no prepared services exist.
Enable `BACKENDBASE_SERVICE_TESTS=1` only for dedicated MySQL 8.4 and Redis 8 targets.
Set their test ports and test credentials explicitly.
MySQL fixtures create and remove only uniquely named `backendbase_adr_*` databases.
Redis session fixtures use generated Account namespaces. Verification fixtures use unique contact and client keys and remove those keys.

Native tests cover these requirements:

| Requirement | Test |
| --- | --- |
| Stable JWT claim meanings and field names | `JwtContractTest` |
| Binary UUID, virtual projection, and schema parity | `MySqlMappingTest` |
| Turkish comparison, Unicode composition, and NO PAD uniqueness | `MySqlCollationTest` |
| Optimistic conflict and lock-timeout retry | `MySqlConcurrencyTest` |
| Error 1213 rollback with a fresh retry unit | `MySqlDeadlockTest` |
| Active-mobile uniqueness, duplicate rollback, and reuse after Account closure | `MySqlSoftDeletionTest` |
| Single contact proof consumption and deadlines after observed lock waits | `MySqlContactVerificationTest` |
| Contact cooldown, contact/IP budgets, and successful confirmation release | `RedisVerificationLimiterTest` |
| Native token/session activation, expiry, and revocation | `RedisSessionTest` |
| RedisJSON fields, automatic key expiry, and existing string-record compatibility | `RedisJsonSessionTest` |
| Activation conflicts and cleanup after JSON or expiry failures | `RedisJsonAtomicityTest` |
| Missing, restored, and reconnected authentication state | `RedisRecoveryTest` |
| Sentinel promotion, primary failure, stale replicas, and lost quorum | `RedisFailoverTest` |
| HTTP replay migration, durable claims, encrypted responses, conflicts, expiry, cleanup, and index plans | `MySqlReplayTest`, `MySqlHttpReplayTest` |

Concurrency workers use separate PHP processes and database connections.
Tests wait for an observed InnoDB lock wait before allowing the competing transaction to proceed.
Contact registration tests use the actual context and evidence locks. SQLite does not prove those lock waits.

## Latest isolated regression evidence

The full suite passed on 18 October 2026 with configurable registration and kebab-case configuration changes present.

| Evidence | Recorded value |
| --- | --- |
| Source | Working tree based on `ea72606db607e1d8951249da6be81d0d959ec417` |
| Operator | Automated local fixture runner |
| UTC interval | 13:13:22 to 13:18:56, including preparation, verification, deployment fixtures, and cleanup |
| Runtime | PHP 8.5.6, PHPUnit 13.4.3, PCOV 1.0.12 |
| Services | MySQL 8.4.11; standalone Redis, failover nodes, and Sentinels use Redis 8.10.2 |
| MySQL and Redis project | `backendbase-native-verification-1792329202-26171`; localhost ports 52678 and 52677 |
| Failover project | `backendbase-redis-failover-1792329207-26195`; separate bridge network and random localhost ports |
| Data and schema | Generated records in unique MySQL databases. MySQL and Redis data use temporary memory mounts. |
| Suite result | 1,527 tests, 72,701 assertions, zero failures, zero errors, zero skips |
| Coverage | 6,858 of 6,858 executable lines; 1,604 of 1,604 methods |
| Cleanup | Both projects have no remaining containers, including stopped containers, networks, or volumes. Exact project-label checks confirm cleanup. |

All 64 previously skipped identities pass: 33 MySQL, 23 standalone Redis, and eight Sentinel cases.
Each class and case name matches the saved default-suite skip list.
Deployment, rollback, fresh-installation, maintenance-failure, and release-manifest compatibility checks pass.
No code correction was required for this run.

Local evidence is under `artifacts/service-verification/registration`.
It includes baseline and native JUnit results, coverage, matched case identities, service versions, target ports, logs, and cleanup records.
Sentinel cases retain their isolated SQLite identity store.
Live HTTP acceptance, index-plan measurements, security scans, and static analysis retain their earlier verification scopes.

## HTTP replay evidence on 18 October 2026

The full suite passed on 18 October 2026 after adding native HTTP replay coverage.

| Evidence | Recorded value |
| --- | --- |
| Source | Working tree based on `3782cbe22456209971a28be68380b9817e6abf77` |
| Operator | Automated local fixture runner |
| UTC interval | 06:52:07 to 07:00:35, including preparation, verification, deployment fixtures, and cleanup |
| Runtime | PHP 8.5.6, PHPUnit 13.4.3, PCOV 1.0.12 |
| Services | MySQL 8.4.11; standalone Redis, failover nodes, and Sentinels use Redis 8.10.2 |
| MySQL and Redis project | `backendbase-native-verification-1792306327-48213`; localhost ports 56974 and 56976 |
| Failover project | `backendbase-redis-failover-1792306523-48654`; separate bridge network and random localhost ports |
| Data and schema | Generated records in unique MySQL databases. Service data uses temporary storage. |
| Suite result | 1,422 tests, 61,118 assertions, zero failures, zero errors, zero skips |
| Coverage | 6,805 of 6,805 executable lines; 1,594 of 1,594 methods |
| Cleanup | All generated containers, networks, and volumes removed. Exact project-label checks confirm cleanup. |

All 63 previously skipped identities pass: 32 MySQL, 23 standalone Redis, and eight Sentinel cases.
The added native HTTP replay case also passes. Deployment, rollback, and fresh-installation fixtures pass.
PHPStan level 8, complexity, and coding standards pass.

Fresh-installation dry-run and execution reach `Version20261018000000` through all six migrations.
Existing replay tables reject repeated creation. Two independent MySQL connections verify active-request rejection and completed response replay.
Native HTTP tests verify encrypted responses, changed-body rejection, fixed expiry, and cleanup.
Unresolved claims remain blocked after advancing the test clock by 30 days.

On 1,000 generated replay rows, the lookup selects `PRIMARY` with constant access and one estimated row.
The cleanup selects `http_request_replay_expiry_idx` with range access and 20 estimated expired rows.
These plans verify fixture index selection. They do not measure production performance.

The production HTTP entry point passes all 31 UserApi and SystemAdminApi operations through 59 initial requests.
An additional 39 retries verify 33 unchanged response replays and six revoked-credential rejections.
Another 21 requests change body bytes under the same key and receive 422.
The temporary application uses real MySQL and Redis adapters and a private contact-notification sink.
No ZAP scan runs in this verification.

Local evidence is under `artifacts/service-verification/idempotency`.
It includes JUnit, coverage, skipped-case identities, migration SQL, schema details, index plans, HTTP results, and cleanup records.

## Contact registration evidence on 18 October 2026

The full suite passed on 18 October 2026 after correction of a concurrent registration conflict response.

| Evidence | Recorded value |
| --- | --- |
| Source | Working tree based on `bfbd30fa3ca249e1263df6145772ecb9ebcbe65a` |
| Operator | Automated local fixture runner |
| UTC interval | 05:13:16 to 05:18:00, including startup, deployment fixtures, and cleanup |
| Runtime | PHP 8.5.6, PHPUnit 13.4.3, PCOV 1.0.12 |
| Services | MySQL 8.4.11; standalone Redis, failover nodes, and Sentinels use Redis 8.10.2 |
| MySQL and Redis project | `backendbase-native-verification-1792300396-32492`; localhost ports 54898 and 54897 |
| Failover project | `backendbase-redis-failover-1792300401-32517`; separate bridge network and random localhost ports |
| Data and schema | Generated fixture records in unique MySQL databases. Service data uses temporary storage. |
| Suite result | 1,350 tests, 56,922 assertions, zero failures, zero errors, zero skips |
| Coverage | 6,594 of 6,594 executable lines; 1,556 of 1,556 methods |
| Cleanup | All generated containers, networks, and volumes removed. Exact project-label checks confirm cleanup. |

The run passes all 62 previously skipped cases: 31 MySQL, 23 standalone Redis, and eight Sentinel cases.
Each case identity matches the recorded skipped-case list. Deployment, rollback, and fresh-installation fixtures also pass.
PHPStan level 8, complexity, and coding standards pass.

The competing registration now receives `IdentityConflict` after another transaction consumes its proof.
Freshness is checked before consumed owner references. Evidence locks still precede the authorization time.
Unknown-contact HTTP requests and mismatched verification realms have explicit rejection tests.

A separate MySQL fixture contains 2,048 generated contact records for query-plan checks.
The context lookup selects `contact_verification_context_slot_unq`. The proof lock lookup selects `PRIMARY`.
Both plans use `const` access and estimate one row. The fixture database is removed after the check.
These plans verify index selection. They do not measure production performance.

Local evidence files are `artifacts/service-verification/native-suite.json` and `artifacts/service-verification/contact-registration-explain.json`.
The Sentinel cases retain their isolated SQLite identity store. Live security scans retain their earlier verification scope.

## Isolated regression evidence on 16 October 2026

The full suite passed on 16 October 2026 with all service tests enabled. No code correction was required.

| Evidence | Recorded value |
| --- | --- |
| Source | Working tree based on `726e8def449e9d4f0e8fbaa0b9ff3a81fc049b05` |
| Operator | Automated local fixture runner |
| UTC interval | 19:44:58 to 19:48:38, including startup and cleanup |
| Runtime | PHP 8.5.6, PHPUnit 13.4.3, PCOV 1.0.12 |
| Services | MySQL 8.4.11; standalone Redis, failover nodes, and Sentinels use Redis 8.10.2 |
| MySQL and Redis project | `backendbase-native-verification-1792179898-61576`; localhost ports 57898 and 57897 |
| Failover project | `backendbase-redis-failover-1792179903-61618`; separate bridge network and random localhost ports |
| Data and schema | Generated test records only. MySQL fixtures use production Doctrine metadata or committed migration fixtures in unique databases. |
| Suite result | 1,160 tests, 30,476 assertions, zero failures, zero errors, zero skips |
| Coverage | 6,306 of 6,306 executable lines; 1,497 of 1,497 methods |
| Cleanup | All generated containers and networks removed. Service data used temporary storage. |

The run executed all 55 previously skipped cases: 24 MySQL, 23 standalone Redis, and eight Sentinel cases.
The recorded test identities match the earlier skipped-case list. Deployment, rollback, and fresh-installation fixtures also passed.
The Sentinel tests retain their isolated SQLite identity store. Production acceptance and provider-specific durability measurements remain separate.
See the [quality report](../../docs/code-quality-and-maintainability-report.html#evidence) for generated metrics and other checks on the same working tree.

## Schema and logical-reference reconciliation

Run `bin/doctrine orm:validate-schema` against the approved clone.
Inspect schema differences without applying generated DDL.
Compare actual generated expressions, byte order, UUID overrides, unique keys, indexes, collations, and migration records.
Use [database collation procedures](database-collation.md) for inherited defaults and text conversion checks.

The following aggregate scans intentionally inspect the complete identity relationship set:

```sql
SELECT COUNT(*) AS orphanUsers
FROM user u LEFT JOIN account a ON a.id = u.account_id
WHERE a.id IS NULL;

SELECT COUNT(*) AS orphanAdmins
FROM `admin` d LEFT JOIN account a ON a.id = d.account_id
WHERE a.id IS NULL;

SELECT COUNT(*) AS invalidContacts
FROM contact_verification c
LEFT JOIN account a ON a.id = c.account_id
LEFT JOIN user u ON c.realm = 'USER' AND u.id = c.actor_id
LEFT JOIN `admin` d ON c.realm = 'ADMIN' AND d.id = c.actor_id
WHERE (c.account_id IS NOT NULL AND a.id IS NULL)
   OR (c.actor_id IS NOT NULL AND c.realm = 'USER' AND (u.id IS NULL OR u.account_id <> c.account_id))
   OR (c.actor_id IS NOT NULL AND c.realm = 'ADMIN' AND (d.id IS NULL OR d.account_id <> c.account_id))
   OR (c.realm = 'ADMIN' AND (c.account_id IS NULL OR c.actor_id IS NULL))
   OR c.realm NOT IN ('USER', 'ADMIN');
```

Each count must be zero. Soft-deleted parents remain valid historical references.
These maintenance scans use the existing primary keys for joins. They do not require speculative lookup indexes.
Record row counts and recovery evidence before any separately approved cutover.
The repository does not supply automatic conversion of an existing database.

## Representative measurements

Capture `bin/backendbase database:diagnostics` before and after a representative workload.
Record dataset and index sizes, command throughput, p95/p99 latency, lock waits, deadlocks, and buffer use.
Use representative MySQL `EXPLAIN` plans for point lookups, relationship queries, and ordered reads.
Measure revocation-to-first-denial time while requests continue on the test session.
Record the accepted latency, failure, and recovery thresholds before deciding acceptance.
Do not publish fixture timings as production performance evidence.

## Redis and collection operations

Rehearse the selected Redis service failure, failover, and recovery procedure.
Use the [isolated failover runner](redis-failover-tests.md) for repeatable local Sentinel evidence.
Provider-specific deployment acceptance remains separate from this fixture.
Verify existing revoked sessions remain denied or require controlled namespace invalidation.
Follow [session recovery](identity-session-recovery.md) when revocation durability is uncertain.
Record exporter, storage, retention, alert thresholds, and incident ownership before production activation.
Existing diagnostics supply aggregate signals. They do not configure dashboards or a metrics collection service.
