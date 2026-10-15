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

Concurrency workers use separate PHP processes and database connections.
Tests wait for an observed InnoDB lock wait before allowing the competing transaction to proceed.
Contact registration tests use the actual context and evidence locks. SQLite does not prove those lock waits.

## Schema and logical-reference reconciliation

Run `bin/doctrine orm:validate-schema` against the approved clone.
Inspect schema differences without applying generated DDL.
Compare actual generated expressions, byte order, UUID overrides, unique keys, indexes, collations, and migration records.
Use [database collation procedures](database-collation.md) for inherited defaults and text conversion checks.

The following aggregate scans intentionally inspect the complete identity relationship set:

```sql
SELECT COUNT(*) AS orphanUsers
FROM identity_user u LEFT JOIN identity_account a ON a.id = u.account_id
WHERE a.id IS NULL;

SELECT COUNT(*) AS orphanAdmins
FROM identity_admin d LEFT JOIN identity_account a ON a.id = d.account_id
WHERE a.id IS NULL;

SELECT COUNT(*) AS invalidContacts
FROM contact_info c
LEFT JOIN identity_account a ON a.id = c.account_id
LEFT JOIN identity_user u ON c.realm = 'USER' AND u.id = c.actor_id
LEFT JOIN identity_admin d ON c.realm = 'ADMIN' AND d.id = c.actor_id
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
