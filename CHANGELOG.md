# Changelog

This file records repository changes in chronological order.

Update the `Unreleased` section in every commit. Include the changelog entry in the same commit as the related change.

This changelog starts on 29 August 2026. Use the Git history for earlier changes.

## Unreleased

### Added

- Added this changelog and the per-commit maintenance rule.
- Added a strict 100% executable-line coverage gate to `composer test`.
- Added focused tests for previously uncovered validation, persistence, messaging, HTTP, AWS, and collection behavior.
- Added validated PHPStan array shapes and typed settings objects for infrastructure configuration.
- Added immutable AWS, SQS, SNS, RabbitMQ, HTTP, JWT, and application runtime settings types.
- Added local documentation-link validation for HTML and Markdown files.
- Added generated quality-report metrics from PHPUnit, Clover coverage, and repository inventory.
- Added safe SQS handler-failure diagnostics with queue and message identifiers.
- Added strict integer and finite-float environment parsers with focused invalid-input tests.

### Changed

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
- Updated the light-themed quality and strengths reports with current verification results and resolved findings.
- Reassessed dependency maintenance cost after removing unused packages and redundant direct requirements.
- Moved OpenAPI and YAML build tools to development dependencies while keeping release contract generation deterministic.
- Deferred the Redis socket until the first Redis command by injecting a lazy RedisJSON proxy.
