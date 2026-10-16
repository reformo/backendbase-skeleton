# Changelog

This file records repository changes in chronological order.

Update the `Unreleased` section in every commit. Include the changelog entry in the same commit as the related change.

This changelog starts on 29 August 2026. Use the Git history for earlier changes.

## Unreleased

### Removed

- Replace User and System Admin usernames with Account email or mobile login. Require globally unique active E.164 mobile numbers at registration. Update initial identity migration, API contracts, operator commands, contact responses, tests, and identity guidance.

- Remove TOTP enrollment, second-step login, factor storage behavior, and factor recovery and rewrapping commands. Password login now returns a session after ownership, Account state, and actor approval checks. Update both identity APIs, Bruno requests, tests, and authentication policy.

- Remove top-level `statusCode` from successful response envelopes. Preserve success values and Problem Details `status`. Update identity OpenAPI sources and merged YAML, Bruno assertions, regression tests, and HTTP contract guidance.

- Removed the IdentityAndAccess migration, privilege seeder, and seeder test. Updated the release migration target to the latest remaining migration.
- Removed the IdentityAndAccess bounded context, account and authentication operations, and Greeting HTTP endpoints with their OpenAPI and Bruno definitions.
- Removed orphan JWT configuration and DAST account provisioning. DAST uses API-key-protected Example writes. Internal QueueGreeting authorization and queue delivery remain available.

### Added

- Add a PHP message viewer for local MiniStack SMS logs and captured SES emails. Include automatic refresh, filtering, and isolated HTML or plain text previews. Add parser, process, HTTP, and preview regressions.

- Add live checks for all 28 identity API operations and authenticated ZAP scans for both realms. Use disposable fixtures, a local contact sink, and sanitized reports. Pin Semgrep scanner content and the full rule snapshot.
- Add guarded maintenance installation for an empty MySQL database. Preserve normal rollback compatibility checks. Test existing-schema rejection and backup, migration, activation, and readiness failures.

- Add nullable Account first and last names, API profile fields, and a manual initial Admin seeder for Mehmet Korkmaz. Require a password file and local root authority. Preserve credentials on repeated runs and keep seeding separate from migrations.

- Require email and mobile verification within 12 hours before User registration. Store hashed codes and scoped evidence in `contact_info`. Add optional Admin verification, bounded email/SMS delivery, quotas, atomic proof consumption, and updated API contracts and migration drafts.

- Add a disposable Redis primary, replica, and three-Sentinel test environment. Verify planned promotion, primary failure, lost revocations, and lost Sentinel quorum in both authentication realms. Run the failover tests in quality and release gates.

- Add native MySQL regressions for Turkish collation and NO PAD uniqueness, deadlock rollback and retry, active-login restore conflicts, and challenge creation during User removal. Add JWT contract and Redis recovery regressions.
- Add database collation, session recovery, and identity acceptance procedures. Reconcile the architecture report with the current JWT contract, identifier scope, password authentication, and fresh-installation migration support.

- Add deterministic tests for Redis session validation and revocation, connection reuse, transaction retries and rollback, managed-record locks, stale revisions, generated projections, and recovery record locks.

- Add required indexes for Example group reads, stable entry pagination, and cross-consumer message cleanup. Add index coverage, SQLite query-plan checks, and an opt-in MySQL migration check.
- Require index review and missing-index additions for new or changed selective queries. Record the runtime SQL index inventory and pin the final consolidated installation migration.
- Require declared OpenAPI tags on every operation. Add source and merged-document tag validation, regression tests, and endpoint grouping guidance.
- Add per-API OpenAPI generation and validation commands for UserApi and SystemAdminApi. Include both merged YAML documents in local housekeeping.
- Add local root identity operator actions for audited first-Admin enrollment, lost-TOTP recovery, and persistent factor key rewrap.
- Create the final Example binary UUID schema directly for fresh installations. Existing integer-key databases require a separately reviewed conversion and migration-history adoption plan.


- Implemented IdentityAndAccess with global Accounts, separate User and SystemAdmin realms, contact ownership challenges, password and TOTP authentication, and Redis token and session allowlists. Added strict HTTP contracts and Bruno request collections.
- Added assigned binary UUIDv7 identity records, read-only generated projections, optimistic versions, selective locks, soft deletion, active key uniqueness, and historical actor projections. Added forward migration drafts and a table ownership inventory.
- Added preview-first outbox replay and external-outcome resolution with operator evidence. Enforced a seven-day replay window and protected recovery records under the minimum 30-day retention policy.
- Added Linux systemd service and timer templates, safe request measurements, aggregate MySQL diagnostics, and isolated MySQL 8.4 and Redis 8 verification in quality and release workflows.
- Added an [ADR browser](resources/adr/index.html) with all records, search, status filters, and a wide accessible Markdown modal. Added an offline document snapshot and a rebuild and parity-check command.
- Added ADR-0036 through ADR-0048 for the final identity, authentication, UUIDv7, Doctrine, collation, relationship, concurrency, reporting, and migration decisions. Added an English decision-source reference for the 13 October 2026 report.
- Added 35 platform Architecture Decision Records under `resources/adr`, using the ADR template and excluding ExampleCatalog-specific decisions. Recorded Mehmet Korkmaz as owner and decider with the date `2026-08-31`.
- Added an Architecture Decision Record (ADR) template at [resources/adr/template.md](resources/adr/template.md).
- Added Claude Code counterparts for the five agent roles, Sonnet and Opus model defaults, and shared project instructions.
- Added five project-scoped Codex agents, project model defaults, and a coordinated planning, implementation, and review workflow.
- Added a project-owned UTC clock for messaging availability, retries, claims, age checks, and retention. Added fixed-clock boundary tests and production binding coverage.
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

### Fixed

- Validate Example detail encoding, decoding, and member names before aggregate or command mutation. Bound detail decoding to 64 levels so accepted values fit event envelopes.
- Preserve nested JSON objects and lists through the outbox, SQS, RabbitMQ, and registered version-one consumer. Add complete serialization and atomic-validation regressions.
- Reject invalid Example detail shapes, identifiers, and oversized fields before writes. Preserve text and nested JSON values through create, update, and read operations.
- Align Example OpenAPI types, field limits, DELETE target parameters, and 400/409 responses. Remove implicit image URL generation. Preserve released event names and payload fields.
- Match memory entry pagination to Doctrine ordering by creation time and UUID. Add boundary, domain, persistence, and API contract regressions. Update reference documentation and endpoint guidance.

- Complete adapter direction classification. Reject unknown categories and forbidden cross-direction dependencies. Protect shared diagnostics from domain and infrastructure dependencies.

- Exclude the absent shared Doctrine entity directory from discovery in fresh checkouts. Add an isolated regression for absent and present directories. Refresh quality report metrics from the full isolated service test run.

- Use a TLS stream context only for configured Redis TLS endpoints. Plain Redis connections no longer attempt a TLS handshake.

- Exclude the DBAL-owned operator evidence table from the native recovery test's ORM schema comparison.

- Audit all 38 API operations for response contract parity. Use `apiMetadata` for Example metadata, declare required Example response fields and list timestamps, preserve empty JSON objects, and return complete catch-all Problem Details. Align Bruno health assertions and add source, merged, Bruno, and runtime response checks.

- Align Slim Problem Details titles with HTTP reason phrases, including `Not Found` for 404 and `Method Not Allowed` for 405. Preserve mapped domain titles and safe 500 responses. Extend HTTP and route regression tests and update error contract guidance.

- Synchronize Problem Details `status` with the HTTP response status for Slim, domain, and unexpected exceptions. Fix missing-route bodies that reported 500 with HTTP 404. Add regressions for both identity APIs, Example not-found responses, and HTTP exception mappings.

- Load production Doctrine type registration in the standalone query test, including `uuid_binary`.
- Flushed ORM changes before integration-event and inbox completion commits. Cleared failed ORM state and used fresh identity units during bounded retries.
- Stopped queue consumers after an ORM flush closes the persistence session. Preserved bounded retry outcomes and supervisor recovery.
- Reloaded actor authorization under Account-first locks to prevent a concurrently removed Admin from restoring itself.
- Removed event objects and business payloads from domain listener and integration subscriber diagnostic logs.
- Bounded S3 multipart retries and signing expiry, enforced exact upload key and content type, and preserved download content type.
- Removed exception messages and call arguments from HTTP and queue diagnostics. Applied body and metadata validation before parsing and logger creation.

### Changed

- Reconcile project, security, testing, and skill documentation with tag-based controller modules and group-owned realm policies. Remove stale API-key-only identity claims from skill guidance.

- Use ExampleApi-style fluent route declarations in UserApi and SystemAdminApi modules. Define each group's realm once through RealmPolicy. Keep action and contact policies independent of realm selection. Apply shared realm authentication to protected route groups. Verify missing-token and cross-realm rejection for all protected routes.

- Require OpenAPI tag-based controller modules in project instructions and API authoring and review skills. Use the ExampleApi ModuleConfig and Handlers layout for new endpoints.

- Move UserApi and SystemAdminApi controllers into their own inbound directories. Group endpoint handlers by OpenAPI tag with ExampleApi-style ModuleConfig classes and module registries. Align route names with operation IDs. Move reusable identity HTTP helpers to Adapters/Http/Identity. Preserve public routes and authentication behavior.

- Fill the environment example with valid local identity settings and commented optional provider, Redis authentication, and key rotation examples. Document the public development keys and required deployment replacements.

- Move the optional Agent Workflow into a separate platform document while preserving its rules. Add short ExampleCatalog and IdentityAndAccess instructions and shared context routing for both clients. Remove `CLAUDE.md` and use `AGENTS.md` directly in Claude Code.

- Replace the Semgrep dummy Slack webhook with an equivalent regex exclusion. Record upstream and adjusted snapshot digests.

- Simplify identity forwarding layers and preserve transaction, expiry, delivery, and response behavior. Make Example audit instants explicit through the injected clock. Add exact timestamp assertions and a detailed assessment scorecard with category weights, criterion points, and deductions.

- Complete the six required quality improvements and reassess code quality at 9/10. Record 998 passing service tests without skips, current coverage, live identity checks, pinned scans, and guarded fresh-install evidence. Preserve optional design costs and target-specific production acceptance as open items.

- Simplify SQS wait-time normalization while preserving queue behavior. Restore the class-file size limit.

- Move login principal creation and current-session identity checks onto Account and Actor behavior. Preserve JWT claims, persistence mappings, and HTTP contracts. Add domain regressions for realm, membership, ownership, approval, closure, and removal.

- Resolve the dependency-integrity assessment with a clean isolated installation of all 141 locked packages. Attribute local differences to configured AWS pruning and PHPCS configuration. Clarify verification before trusted install transformations.

- Add a prioritized plan toward 9/10 code quality. Define dependencies, completion evidence, required fixes, optional design improvements, and separate production acceptance requirements.

- Refresh the engineering and architecture assessments at `df29f9c`. Record current strengths, adapter direction-test gaps, identity snapshot coupling, dynamic security scope, the local dependency-content failure, and the rollback-unsafe release gate. Separate local execution from recorded service coverage. Align project, testing, authentication, and operations guides with the three current APIs.

- Store JWT token and session allowlists as RedisJSON through the existing RedisJSON library. Apply key-level `EXPIRE` after each `JSON.SET` inside atomic Lua activation. Align the deadline with JWT expiry through `EXPIREAT`. Preserve JWT claims, key names, revocation, and reads of existing string records until expiry. Add native JSON, TTL, conflict, failure-cleanup, and failover regressions.

- Set MySQL server defaults to `utf8mb4` and `utf8mb4_tr_0900_ai_ci` for fresh database initialization. Keep existing-database conversion explicit.

- Update `aws/aws-sdk-php` to `3.401.2` and `symfony/polyfill-deepclone` to `v1.43.2`. Refresh the Composer lock file, software bill of materials, and package content digests.

- Group all OpenAPI endpoint definitions in feature folders and reference each path from its API root. Preserve all 38 operation contracts. Update contract tests, project rules, platform guidance, and API skills for the source layout.

- Replace generic success `data` wrappers with resource and result keys, including `user`, `admin`, `registration`, `challenge`, `enrollment`, and `session`. Preserve payload fields and HTTP/error contracts. Update OpenAPI sources, merged YAML, Bruno assertions, regression tests, and guidance. Require descriptive response keys in project and skill rules.

- Consolidate seven migration drafts into three final-schema installations for messaging, ExampleCatalog, and IdentityAndAccess. Include recovery and operator evidence and required indexes at creation. Remove the staged Example conversion from fresh installation. Update release metadata, regression tests, and existing-database adoption guidance.

- Declare all identity Doctrine fields and mapping methods in their entity classes. Remove mapped superclasses, preserve database mappings, and enforce direct field ownership in tests and guidance.
- Derive mapped Doctrine entity class names from full table names in PascalCase. Rename Example and Identity entities and update references and guidance.

- Set the PHPUnit working directory to the project root. Use root-relative paths for project configuration and test fixtures. Reuse the console project root for Dotenv.
- Disabled persistent merged-configuration and container credential caches. Added separate signing-key and encrypted-factor rotation settings. Managed secret-provider selection remains a deployment decision. Local root identity commands record separate authorization evidence for privileged recovery.
- Synchronized accepted ADRs, platform guidance, API contracts, operations, skills, and the ADR implementation register with the implemented behavior.
- Applied ADR strict object-mapping defaults, safe mapping errors, language-correct English translations, and release coverage configuration.
- Accepted ADR-0025 Option A for a bounded replay window with matching inbox retention. Recorded identity preservation, age enforcement, recovery margins, and cleanup protection requirements.
- Accepted ADR-0007 Option A for managed servers with supervised application processes. Recorded process ownership, server hooks, scheduling, and recovery verification requirements.
- Accepted ADR-0048 Option B for realm-scoped actor login names with explicit normalization. Aligned ADR-0044 and recorded lookup, uniqueness, and lifecycle verification requirements.
- Accepted ADR-0026 Option B for inbox leases with manual outcome resolution. Recorded evidence requirements, blocked unknown outcomes, and controlled retry conditions.
- Accepted ADR-0032 Option A for strict boundary validation with explicit mapping exceptions. Recorded default rejection rules, contract compatibility, and exception verification requirements.
- Accepted ADR-0008 Option A for a shared database with exclusive context table ownership. Aligned logical-reference guidance and the source comparison with the selected ownership policy.
- Accepted ADR-0011 Option A for a managed secret store with controlled runtime injection. Recorded access, rotation, revocation, and process-refresh requirements.
- Accepted ADR-0047 Option B for supported read-only Doctrine mapping of the generated UUID projection. Recorded persistence and schema-diff checks for implementation.
- Replaced Redis Stack 7.4 with pinned Redis 8.10.2 images in Docker Compose and the security workflow. Preserved Redis data storage and the local Redis Insight interface through a separate pinned service.
- Required an explicit user request before starting the Agent Workflow or subagents. The main agent completes tasks alone by default.
- Reconciled seven existing ADRs with the final report. Superseded ADR-0029 with realm-specific authentication and accepted the minimum observability signal policy. Preserved unresolved choices and recorded target implementation gaps.
- Consolidated Example table creation and active lookup uniqueness into `Version20260823215059`. Updated release metadata and migration-history adoption guidance.
- Changed Example writes to API-key protection only. Removed bearer and privilege inputs while preserving validation, transactions, and response identifiers.
- Updated current API, configuration, queue, platform, and reusable skill guidance. Preserved dated assessment evidence.

- Enabled Fast mode for the Codex implementer.
- Aligned project, testing, maintenance, and reusable verification guidance with recent messaging, CQRS, Composer, and project-copy changes. Refreshed the interactive architecture map and its source references.
- Documented identifier responses and application orchestrators for resource representations after writes. Aligned platform rules, guides, and reusable skills while preserving `void` command handlers.
- Refreshed the engineering quality report from full-suite coverage and test results after adding the messaging dependency boundary.
- Moved technical messaging coordination to `Infrastructure/Messaging`. Preserved port bindings and behavior, moved its tests, and added a dependency boundary for the new directory.
- Moved the agent-memory validation script to `bin/validate-agent-memory.sh` and clarified its output label.
- Moved the local Nginx CDN template to `resources/docker` and updated its Docker Compose mount.
- Set Monolog timestamps explicitly to UTC, independent of the process time zone. Preserved explicit time-zone support in the date helper and documented audit-only timestamp usage.
- Renamed consumer API entry code from `Infrastructure/UseCase` to `Infrastructure/Inbound` and moved operational console commands to `Infrastructure/Adapters/Console`. Renamed the API selector to `ConsumerApiTarget` and updated bootstrap, tests, and guidance.
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
