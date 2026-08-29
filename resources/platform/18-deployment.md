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

Clear merged configuration, route, container, and Doctrine metadata caches when their sources change. Restart workers after activation.

Keep the recorded previous release available. Prefer forward repair after partially committed MySQL data-definition changes.

Current deployment scripts require server-specific backup and process-activation hooks. `deployment/release.json` supplies migration and rollback policy for the generated `release-manifest.json`. The repository provides no worker supervisor, scheduler, or alerts.

Basis: `resources/docs/12-deployment-and-operations.html`.
