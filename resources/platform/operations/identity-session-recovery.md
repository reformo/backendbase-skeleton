# Identity Session Recovery

JWT validation requires current Redis allowlist records and current MySQL Account and actor state.
The runtime uses one configured Redis endpoint. Replica selection and failover belong to the selected Redis service.
The local Docker service is a single Redis instance.

## Recovery boundary

Missing or mismatched token or session records deny access.
A Redis command or connection failure returns the authentication-unavailable error contract.
The PHP Redis client can reconnect automatically after a closed socket. Reconnection must still apply every validation gate.

Current MySQL state denies tokens after Account closure or an authentication-version change.
It does not retain token-only or session-only revocations.
Restoring an older Redis snapshot can therefore restore authority removed by logout or token revocation.
Do not restore old identity allowlists as active authentication state.

## Restore or uncertain failover

1. Stop authentication traffic on the affected deployment.
2. Identify the affected Redis endpoint, realms, and Account namespaces.
3. Preserve diagnostic evidence without tokens, credentials, or raw identity records.
4. Establish whether acknowledged revocations survived the failure.
5. Invalidate affected `identity:{REALM:accountId}:jti:*` and `sid:*` records when that evidence is incomplete.
6. Include both USER and ADMIN namespaces.
7. Keep current MySQL Account and actor state authoritative.
8. Reopen traffic after readiness and rejection checks pass.
9. Require a new password login for affected sessions.

Namespace invalidation is an external write. Apply it only to an identified, authorized target.
Use bounded scanning on that target. Do not use `FLUSHDB` or `FLUSHALL` on a shared Redis database.
Clearing a namespace requires controlled traffic so new sessions cannot race with the invalidation.
Authentication must remain stopped when the affected namespace cannot be established.

## Evidence

`RedisRecoveryTest` exercises native Redis `DUMP` and `RESTORE` for fixture-owned records.
It verifies rejection after total loss, partial restoration, and client reconnection.
It also restores records after MySQL Account closure and verifies that both realms remain denied.
That Account check uses the isolated SQLite identity fixture. It tests the Redis recovery boundary, not cross-service commit atomicity.

`RedisFailoverTest` runs real Sentinel elections in a disposable primary, replica, and three-Sentinel topology.
It verifies planned promotion, primary failure, loss of revocation writes, and loss of Sentinel majority in both realms.
The stale-replica case proves why controlled logout is required when acknowledged revocations are lost.
See the [isolated failover runner](redis-failover-tests.md) for commands, boundaries, and cleanup.

These tests establish the local topology's behavior. They do not establish a managed service's durability or recovery objectives.
Before production acceptance, rehearse the selected deployment with issued and revoked sessions.
Record the recovery-point policy, outage interval, revocation durability, namespace invalidation, and fresh-login result.
Use [identity acceptance](identity-acceptance.md) to record the target and results.
