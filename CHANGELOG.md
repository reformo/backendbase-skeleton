# Changelog

This file records repository changes in chronological order.

Update the `Unreleased` section in every commit. Include the changelog entry in the same commit as the related change.

This changelog starts on 29 August 2026. Use the Git history for earlier changes.

## Unreleased

### Added

- Added Doctrine ORM account write metadata and shared Doctrine and memory repository contract tests.
- Added database-backed example account authentication and ACL privilege seeds.
- Added account registration, revision, retirement, and listing operations with ACL checks.
- Added this changelog and the per-commit maintenance rule.
- Added a strict 100% executable-line coverage gate to `composer test`.
- Added focused tests for previously uncovered validation, persistence, messaging, HTTP, AWS, and collection behavior.
- Added validated PHPStan array shapes and typed settings objects for infrastructure configuration.
- Added immutable AWS, SQS, SNS, RabbitMQ, HTTP, JWT, and application runtime settings types.
- Added local documentation-link validation for HTML and Markdown files.
- Added generated quality-report metrics from PHPUnit, Clover coverage, and repository inventory.
- Added safe SQS handler-failure diagnostics with queue and message identifiers.
- Added strict integer and finite-float environment parsers with focused invalid-input tests.
- Added a weekly Composer audit and isolated dependency-review workflow.
- Added a CycloneDX SBOM and reviewed SHA-256 content digests for all locked Composer packages.
- Added an opt-in Moto integration profile and a tracked environment example for local S3, SQS, and SNS endpoints.
- Added reviewed Composer public keys for deterministic isolated self-diagnosis.

### Changed

- Updated the engineering quality and Ports and Adapters reports for the current account lifecycle, architecture boundaries, verification evidence, and light-only presentation.
- Standardized OpenAPI and runtime path parameter identifiers on `kebab-case` and synchronized Bruno and API documentation.
- Revoked all active account authorization state before account revision or retirement.
- Aligned API-key and bearer failures with stable 401 problem responses and validated authorization request state.
- Allowed replacement registration after account retirement through active-email uniqueness.
- Moved ExampleApi bearer middleware into infrastructure and moved IdentityAndAccess tests into the context.
- Moved IdentityAndAccess port bindings into its context service provider.
- Added strict account email, password, and privilege input limits to runtime and OpenAPI contracts.
- Updated reusable security and bounded-context skill references for the corrected runtime paths and behavior.
- Made platform and skill routing mandatory before implementation, after scope changes, and during final verification.
- Reduced direct runtime dependencies from 42 packages to 37 packages.
- Expanded PHPStan level 8 and PHPCS coverage to operational tools and database PHP.
- Replaced direct nested settings reads in infrastructure composition with validated typed settings objects.
- Replaced runtime `Settings::get()` access and adapter configuration arrays with typed settings injection.
- Limited AWS Software Development Kit arrays to the external client-construction boundary.
- Pinned all GitHub Actions to immutable commit SHAs with version comments.
- Added documentation-link and generated-report drift checks to quality and release workflows.
- Preserved SQS retry behavior while making swallowed handler exceptions observable.
- Replaced weak Redis, RabbitMQ, SQS, and readiness numeric casts with explicit configuration failures.
- Simplified queue-message metadata validation and removed unreachable failure guards.
- Updated the quality, strengths, and Ports and Adapters reports with current verification, dependency, and supply-chain evidence.
- Added a continuous-integration cyclomatic-complexity gate with a maximum of 12 per function or method.
- Improved the quality report with a dependency profile, compact responsive navigation, mobile table cards, keyboard focus states, and print styles.
- Kept ratio metrics on one line in the quality report evidence summary.
- Reassessed dependency maintenance cost after removing unused packages and redundant direct requirements.
- Reassessed dependency and upgrade cost with current freshness, audit, and major-upgrade evidence.
- Moved OpenAPI and YAML build tools to development dependencies while keeping release contract generation deterministic.
- Deferred the Redis socket until the first Redis command by injecting a lazy RedisJSON proxy.
- Pinned Composer 2.10.3 in CI and release workflows, enabled explicit security policy, and removed an unused plugin permission.
- Bound Composer supply-chain evidence to release manifests and verify dependency contents before plugins or scripts execute.
- Added S3-compatible endpoint and path-style request support to the object-store client.
- Merged the strengths and weaknesses assessment into the light-only engineering quality report, then removed the obsolete standalone report.
- Made PHPUnit use explicit test configuration instead of requiring an untracked `.env` file.
- Enabled PHP assertions in the quality-gate runtime so PCOV measures assertion lines consistently.
- Updated the engineering quality and Ports and Adapters reports for database-backed authentication and the complexity limit of 12.
- Reduced the affected configuration and adapter methods to meet the maximum cyclomatic complexity of 12.
