# Deployment and Operations

A release changes code, dependencies, generated contracts, cache, schema, and background processes.

## Release gates

- Select an exact reviewed revision.
- Run tests, PHPStan, and PHPCS.
- Generate and validate OpenAPI.
- Review each migration and recovery path.
- Confirm target secrets and configuration.
- Confirm consumers remain compatible with queued messages.
- Create and verify the immutable release artifact and checksum.
- Apply only the approved migration manifest.
- Switch the active release link atomically.
- Verify readiness, outbox health, workers, and logs.

Clear merged configuration, route, container, and Doctrine metadata caches when their sources change. Restart workers after activation.

Keep the recorded previous release available. Prefer forward repair after partially committed MySQL data-definition changes.

Current deployment scripts require server-specific backup and process-activation hooks. `deployment/release.json` supplies migration and rollback policy for the generated `release-manifest.json`. The repository provides no worker supervisor, scheduler, or alerts.

Basis: `resources/docs/12-deployment-and-operations.html`.
