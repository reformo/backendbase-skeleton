# Managed Server Operations

The production target uses Linux systemd with supervised PHP application processes.
Use the host PHP-FPM service for HTTP workers. Use Nginx for TLS termination and routing.
Use the supplied systemd units for the transactional outbox relay, queue consumers, and message cleanup.

## Installation prerequisites

- Use PHP 8.5 with the Composer platform requirements, including Sodium.
- Prepare MySQL 8.4 with InnoDB and the selected database character set and collation.
- Prepare Redis 8 with authentication and verified TLS for remote connections.
- Give the application user write access only to its runtime directories.
- Install the managed secret agent for the selected provider before process activation.
- Supply a root-owned runtime environment file at `/run/backendbase/runtime.env` with mode `0600`.
- Set the provider agent to replace the runtime file atomically after validated secret retrieval.
- Keep the runtime file outside releases and the web root.

The managed secret provider remains a required deployment selection.
The units consume the controlled runtime injection contract. They do not fetch provider secrets or contain credential values.
Provider-specific authentication and renewal must be configured before activation.

## Process configuration

Adjust installation and PHP binary paths to the identified host.
Keep `BACKENDBASE_ENV=production`. Keep exception arguments disabled.
Point Nginx only at the release `public` directory.
Keep PHP-FPM in the host supervisor. Set its pool to the application user.
Allow PHP-FPM to inherit only the required injected environment variables.

The relay runs one bounded batch every five seconds. Queue consumers restart after failure.
An ORM flush failure stops the consumer when its persistence session closes.
The consumer preserves the message retry or reject outcome before systemd starts a fresh process.
Cleanup retains completed message evidence for at least 30 days.
Pending, unresolved, and required recovery evidence must remain available.

Validate command names and unit files against the installed release before enabling timers.
Start no production process through repository verification tests.

## Release activation

Stop the consumer instances before changing code that handles queued messages.
Verify queue compatibility and the original replay occurrence metadata.
Run the reviewed migration and recovery gates through the existing release workflow.
Switch the active release link atomically.
Remove previous merged-configuration and compiled-container artifacts before activation.
Clear safe metadata and route caches when their sources change.
Restart PHP-FPM and the affected consumer instances.
Verify readiness, outbox health, and process status.

Application configuration and container definitions do not write credential values into persistent caches.
Doctrine and object-mapping caches contain metadata. Route caches contain trusted route definitions.

## Secret rotation

Retrieve the new secret version through the managed provider agent.
Validate the complete key and connection configuration before replacing runtime injection.
Use distinct USER and ADMIN issuers, audiences, and signing keys.
Keep the previous key under a distinct key identifier for the permitted verification window.
Restart PHP-FPM and long-running workers after injection changes.
Verify new issuance and old-key verification before retiring the previous key.
Retire previous signing keys only after their tokens have expired or been revoked.
Ownership proof hashes use the existing `encryptionKey` configuration names.
Retain required contact proof keys through pending code deadlines and confirmed evidence recovery windows of 12 hours.
Pending proof hashes also require their original verification key until expiry or invalidation.

Do not record environment values, passwords, tokens, or provider responses in logs.

Use [identity operator procedures](identity-operator.md) for first-Admin setup.
Use [migration adoption](migration-consolidation.md) before deploying consolidated migrations to an existing database.
Use [Example conversion planning](example-uuid-conversion.md) when an existing database retains integer Example keys.
Use [database collation procedures](database-collation.md) to verify database defaults and preserve column overrides.
Complete [identity acceptance evidence](identity-acceptance.md) before production acceptance.
Use [session recovery](identity-session-recovery.md) after Redis restore or uncertain failover.
