# Changelog

This file records repository changes in chronological order.

Update the `Unreleased` section in every commit. Include the changelog entry in the same commit as the related change.

This changelog starts on 29 August 2026. Use the Git history for earlier changes.

## Unreleased

### Added

- Added attribute and context registry resolvers for command and query handlers. Dependency definitions select the registry by default, and context service providers own handler mappings.
- Added `outbox:relay --continuous` to drain full batches and check for pending rows about every 250 ms.
- Added an Example API greeting endpoint and a queue consumer that prints the submitted full name.
- Added selectable Twilio and Netgsm SMS providers with validated settings and finite HTTP timeouts.
- Added SES and SMTP email providers, conditional Firebase push registration, and typed notification settings.
- Added ordered notification delivery results and partial-batch failure details.
- Added a local StackPort UI for MiniStack resources.
- Added local Nginx file delivery and a 60-second cache for the MiniStack S3 bucket.
- Added deterministic Bruno folder ordering and an ephemeral DAST account fixture for authenticated API coverage.
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

- Reassessed the engineering quality and Ports and Adapters reports against the current source and local checks. Corrected stale architecture counts and separated current evidence from historical and incomplete checks.
- Removed handler attributes and application-handler imports from all command and query contracts. Context registries now provide every current CQRS handler mapping.
- Moved shared HTTP, console, Doctrine, migration, and object mapping support into Infrastructure. The Shared architecture test now checks every Shared file.
- Replaced the Example entry's public array snapshot with a typed snapshot for Doctrine and memory adapters.
- Renamed the reference context to `ExampleCatalog` and its domain symbols to `Entry`, including commands, queries, events, repositories, tests, and the seeder. Updated reusable naming guidance while preserving HTTP, database, and published integration-event contracts.
- Made development housekeeping regenerate and verify Composer supply-chain evidence from an isolated install after dependency updates.
- Consolidated the integration-event outbox, inbox, failure tables, and indexes into the first migration for new databases.
- Aligned reusable skills, platform guidance, and HTML guides with current event dispatch, relay modes, API routes, security, configuration, and source inventory.
- Dispatched integration events inside the producer transaction, running local subscribers for both delivery modes and appending one outbox row only when `DELIVER_VIA_QUEUE` is true.
- Renamed the integration event queue-delivery flag to `DELIVER_VIA_QUEUE`.
- Refreshed Composer supply-chain evidence for the PHPStan 2.2.15 dependency update.
- Corrected empty Composer lock object fields and refreshed quality report metrics for the current test suite.
- Disabled automatic SES email retries so a connection failure cannot cause a second send attempt within one notification call.
- Allowed direct SMS, email, and push requests through `Notify`; separated the provider interface from the caller port.
- Validated push targets and email delivery fields before provider calls, and removed push payload logging.
- Set the local MiniStack SQS queue URL explicitly in the environment example.
- Replaced the local AWS emulator with MiniStack 1.5.14 for S3, SQS, SNS, SES, and CloudFront APIs, and aligned the development environment example. An empty CDN URL now uses a signed S3 URL.
- Made narrow reusable skills select relevant discovery, reference sections, and verification. Preserved existing operation authority across skill steps and moved skill-authoring rules into scoped guidance.
- Scoped agent guidance and feature verification to affected behavior, reused valid discovery and check results, and preserved existing authorization for unchanged targets and scope. Added a five-task comparison with recorded measurements and acceptance evidence.
- Serialized account authentication, revision, and retirement with a shared account-row transaction and refreshed privilege state.
- Removed the 1,000-group result cap and applied group pagination in both persistence adapters.
- Rejected invalid pagination sizes and overflowing offsets before query dispatch.
- Required explicit database-target authority consistently in migration instructions.
- Made new-table migrations fail on unexpected existing tables instead of hiding schema drift.
- Removed the incomplete email notification consumer until a complete message contract and provider exist.
- Applied named privilege guidance to sensitive commands and queries.
- Moved every Example context test into its module-owned test suite.
- Made an empty object-store endpoint use the shared AWS endpoint as documented.
- Aligned Example API details and pagination contracts with runtime behavior.
- Limited repository contract suites to behavior declared by each port.
- Corrected DAST boundary expectations for missing and invalid API keys and bearer tokens.
- Added the required IANA timezone header to authenticated DAST fixture and boundary requests.
- Made Bruno authentication and account captures runtime-only, generated run-unique example keys, and removed the tracked token placeholder.
- Updated the engineering quality and Ports and Adapters reports for the current account lifecycle, architecture boundaries, verification evidence, and light-only presentation.
- Standardized OpenAPI and runtime path parameter identifiers on `kebab-case` and synchronized Bruno and API documentation.
- Revoked all active account authorization state before account revision or retirement.
- Aligned API-key and bearer failures with stable 401 problem responses and validated authorization request state.
- Allowed replacement registration after account retirement through active-email uniqueness.
- Moved ExampleApi bearer middleware into infrastructure and moved IdentityAndAccess tests into the context.
- Updated the IdentityAndAccess port-boundary test to scan the current infrastructure HTTP input path.
- Deferred IdentityAndAccess privilege seeding until the migration creates its tables.
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
