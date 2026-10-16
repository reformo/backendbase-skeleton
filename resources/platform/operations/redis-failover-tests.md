# Isolated Redis Failover Tests

Run this environment with Docker Compose, PHP 8.5, the PHP Redis extension, and installed project dependencies.
Run commands from the repository root:

```sh
bash bin/tests/redis-failover.sh
```

The runner generates a unique `backendbase-redis-failover-*` project name.
Each case recreates one primary, one replica, and three Sentinels from the pinned Redis 8.10.2 image.
The topology uses a separate project bridge network and random host ports bound to `127.0.0.1`.
Redis data uses temporary memory mounts. Sentinel configuration stays inside disposable containers.
The runner removes its containers, volumes, and network on success, failure, or a handled interruption.
It does not join the development network or mount project database volumes.

Docker access grants authority to terminate these generated test containers.
Never point the fixture at a shared Redis deployment.
The generated project-name guard restricts the fixture's Docker commands to this environment.

## Scenarios

Every scenario runs for USER and ADMIN:

| Scenario | Verified behavior |
| --- | --- |
| Planned Sentinel promotion | Replicated active sessions, token revocation, logout, and absolute expiry survive promotion. The demoted primary rejects new session writes. |
| Primary process failure | The original application connection rejects authentication during failure. Sentinel promotes the replica. A new application connection to that endpoint preserves session and revocation checks. |
| Stale replica promotion | Disconnecting replication loses subsequently acknowledged token and session revocations. The promoted replica can accept those old sessions. Controlled Account namespace invalidation rejects them before traffic resumes. A new password login succeeds. |
| Lost Sentinel majority | One remaining Sentinel cannot authorize automatic promotion. Authentication remains unavailable. Restoring the majority permits election and preserves replicated revocations. |

The healthy-state tests use `WAIT` as an explicit replication barrier before injecting failure.
The stale-state test verifies that this barrier is absent for the lost revocations.
It retains the replica's existing data and temporarily selects an unreachable upstream port before stopping the primary.
The runtime does not add `WAIT` to application writes.
Redis replication is asynchronous. Acknowledged writes can be lost during failover.
`WAIT` does not provide strong consistency. See the [Sentinel guide](https://redis.io/docs/latest/operate/oss_and_stack/management/sentinel/) and [WAIT reference](https://redis.io/docs/latest/commands/wait/).

## Application boundary

The tests resolve `RedisJsonInterface` through the production Redis dependency provider and its lazy proxy.
The library uses the shared native `SessionRedis` connection. Replication barriers run through the same library connection as activation.
Token and session allowlists use RedisJSON. Each activation applies `EXPIRE` after `JSON.SET` inside one Lua script.
The same script applies `EXPIREAT` to retain the exact JWT expiration deadline without relative-time rounding.
Planned promotion verifies JSON object types and replicated key expiry.
They use real JWT signing, Redis Lua validation, and current Account and actor checks.
Identity state uses the isolated SQLite fixture. These tests do not establish cross-service atomicity or MySQL failover.
The topology uses plain Redis connections. TLS option tests preserve peer verification without establishing certificate-exchange behavior.

The application has one configured endpoint. It does not discover Sentinel primaries itself.
After promotion, the tests rebuild the connection using the selected endpoint.
Production infrastructure must provide a stable primary endpoint or update runtime configuration and restart affected workers.
An automatic Redis socket reconnect to the old endpoint does not provide primary discovery.

Do not reopen traffic after uncertain revocation durability until affected allowlists have been invalidated.
The fixture clears only its Account's token and session records in both realms.
It preserves Account block records and uses bounded `SCAN` batches.
Follow [session recovery](identity-session-recovery.md) on a separately authorized deployment target.

## Full suite and continuous integration

The runner accepts a verification command:

```sh
bash bin/tests/redis-failover.sh composer test
```

Existing MySQL and standalone Redis service tests still require their own identified, isolated targets and test variables.
The failover runner supplies `BACKENDBASE_REDIS_FAILOVER_TESTS=1` and its generated project name.
Ordinary PHPUnit runs skip these eight cases when that runner is absent.
Quality and release workflows run the full suite through the failover runner while retaining their separate service containers.

The 16 October 2026 isolated run passed all eight failover cases in the full suite with zero skipped tests.
It used Redis 8.10.2 and removed all generated containers and networks after completion.
See [the recorded service evidence](identity-acceptance.md#latest-isolated-regression-evidence) for targets, versions, and the UTC interval.

Startup, Docker commands, replication discovery, and promotion use finite deadlines.
Test thresholds bound fixture execution. They do not establish production recovery-time or data-loss objectives.
Record provider-specific durability, recovery time, authentication behavior, and fresh-login evidence through [identity acceptance](identity-acceptance.md).
