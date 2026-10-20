# Identity Session Recovery

JWT validation requires current Redis allowlist records and current MySQL Account and actor state.
New token and session records are RedisJSON objects with key-level TTL. Account block markers remain strings.
The runtime uses one configured Redis endpoint. Replica selection and failover belong to the selected Redis service.
The local Docker service is a single Redis instance.

## RedisJSON rollout and rollback

The current registry reads both RedisJSON and existing string allowlists. Existing records keep their original expiry during rollout.
New activation writes RedisJSON through the existing library and sets expiry with `EXPIRE` inside the same Lua script.
The remaining JWT lifetime determines the initial TTL in whole seconds.
The script then applies `EXPIREAT` to retain the exact JWT expiration deadline. Authentication reads do not renew it.
Redis access policy must permit the JSON, expiry, read, deletion, type, and scripting commands used by the registry.
An activation error removes its newly created pair before returning an authentication-unavailable error.

Older application versions cannot read RedisJSON allowlists.
Before a rollback, stop authentication traffic and invalidate affected token and session allowlists in both realms.
Preserve Account block markers. Require fresh login after rollback. Execute this procedure only on a separately authorized deployment target.

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

The [local deployment rehearsal](local-deployment-rehearsal.md) also exercises full HTTP authentication through PHP-FPM with real MySQL and Redis.
It tests stale-replica promotion, controlled invalidation in both realms, and current MySQL rejection after Account closure.
It restores a populated database into an independent MySQL container before this recovery check.

These tests establish the local topology's behavior. They do not establish a managed service's durability or recovery objectives.
Before production acceptance, rehearse the selected deployment with issued and revoked sessions.
Record the recovery-point policy, outage interval, revocation durability, namespace invalidation, and fresh-login result.
Use [identity acceptance](identity-acceptance.md) to record the target and results.
