# Deployment and Operations

A release changes code, dependencies, generated contracts, cache, schema, and background processes.

## Release gates

- Select an exact reviewed revision.
- Run tests, PHPStan, cyclomatic complexity, and PHPCS.
- Generate and validate OpenAPI.
- Review each migration and recovery path.
- Confirm target secrets and configuration.
- Confirm consumers remain compatible with queued messages.
- Create and verify the immutable release artifact and checksum.
- Verify the Composer SBOM and package-content manifest before dependency code executes.
- Apply only the approved migration manifest.
- Switch the active release link atomically.
- Verify readiness, outbox health, workers, and logs.

The artifact builder first installs dependencies without plugins or scripts. It verifies the reviewed package-content digests before it enables trusted build scripts. A final `composer install --no-dev` command removes development tools before archive creation.

The release manifest binds the release to `composer.lock`, the CycloneDX SBOM, and the package-content manifest with SHA-256 checksums.

Clear route, Doctrine, and mapping metadata caches when their sources change. Restart workers after activation.
Runtime secrets are injected outside releases. Configuration and container definitions are rebuilt without persistent credential caches.

Keep the recorded previous release available. Prefer forward repair after partially committed MySQL data-definition changes.

Current deployment scripts require server-specific backup and process-activation hooks. `deployment/release.json` supplies migration and rollback policy for the generated `release-manifest.json`. Linux systemd units provide worker supervision and relay and cleanup timers. See [managed server operations](operations/managed-servers.md).
Use [fresh installation](operations/fresh-installation.md) for the current rollback-unsafe release on an empty MySQL database.
The explicit `--fresh-install` mode requires no active or previous deployment and mandatory maintenance, backup, and activation hooks.
It rejects existing schema objects before migration. Normal updates retain the rollback compatibility guard.
The managed secret provider remains a required selection before production activation.
Use the local root identity commands with separate invitation and approval evidence for first-Admin setup.
See [identity operator procedures](operations/identity-operator.md).

Basis: `resources/docs/12-deployment-and-operations.html`.
