# Testing and Quality

Use the smallest check that proves the change. Expand checks as risk increases.

## Test bootstrap

`tests/bootstrap.php` sets `projectRoot` and changes the working directory before loading Composer.
Use root-relative paths for project catalogs, configuration, routes, and migration fixtures.
Module-owned tests can use file-relative paths for fixtures that move with their bounded context.

## Test ownership

- Keep movable context tests under `src/Backendbase/Domain/*/Tests`.
- Keep shared, infrastructure, functional, and architecture tests under `tests`.
- Keep operational-tool tests under `tests/Tools`.
- Use memory adapters for fast in-process lifecycle tests.
- Use production Doctrine metadata and isolated schemas for repository tests.
- Run the same behavioral repository contract suite against every adapter for one port.
- Keep adapter-specific tests for storage details such as mapping and rollback.
- Use deterministic doubles at network and provider boundaries.
- Use `docker compose up -d` for local S3, SQS, SNS, SES, and CloudFront API integration tests.
- Create and remove emulator resources in each integration test setup. Do not add the emulator to the default suite.
- Keep invalid input, rollback, retry, mapping, and not-found paths explicit.
- Keep dependency-direction tests separate for Application, Shared, messaging coordination, inbound adapters, and outbound adapters.
- Verify every CQRS contract has a registry mapping and no handler attribute. Verify domain-listener targets and production-container resolution.

## Select verification by changed behavior

Coordinate checks once for the complete task. The commands below are available checks, not a sequence required for every edit.

| Change | Required local evidence |
| --- | --- |
| Prose or instruction files only | Review meaning and local links. Validate changed skill metadata. No PHP analysis or application suite is needed. |
| PHP behavior | Focused regression and affected failure-path tests, PHPStan level 8, cyclomatic complexity, and PHPCS. |
| Dependencies or layer placement | Affected architecture tests in addition to the code checks. |
| Runtime registration | Tests through the actual container, bus, provider, route map, or registry. |
| Public HTTP contract | Affected boundary tests, generated OpenAPI validation and diff review, and maintained Bruno request alignment. |
| Persistence or schema | Affected repository and mapping tests. Review migration SQL and release metadata when changed. Database execution needs identified target authority. |
| Queue or external integration | Deterministic boundary, failure, retry, and idempotency tests for affected behavior. Service-backed runs need prepared targets. |
| Release tooling | Affected deployment-script tests. |
| Composer metadata | Supply-chain evidence from an isolated install and applicable quality gates. |
| Generated quality metrics | Fresh PHPUnit and coverage evidence, then report parity checks. |

Start with the smallest relevant test. Expand to its owning directory, related integration tests, or the full suite when risk or project policy requires it. Broad changes require the full suite. Keep existing continuous-integration and release gates unchanged.

For typed JSON columns, test validated reconstruction, exact scalar types, nullable and missing fields, and the documented unknown-field policy.
Verify storage round trips, explicit replacement, and unchanged earlier snapshots. Cover empty objects, empty lists, and nested shape preservation where permitted.
For list columns, verify element validation, order, and list shape. For each mapping exception, test its documented contract.

For JSON payload changes, test the producer, persisted outbox, outgoing and incoming transport mappers, and registered consumer carrier together.
Include empty objects, empty lists, nested objects with integer member names, and accepted depth boundaries.
For detail validation changes, verify that rejected values leave all aggregate fields and previous command details unchanged.

Choose a focused test file or its owning directory according to the change. Do not run both only because a skill lists both.

One successful result can satisfy several skills. Reuse it while relevant code, configuration, tests, and the execution environment remain unchanged. Repeat checks after relevant edits, failures, environment changes, or new evidence.

Fix failures introduced by the requested change. Report unrelated existing failures and concrete blockers. Do not broaden implementation solely to make an unrelated check pass. Report only required checks that could not run; omit unrelated command examples.

## Execution boundaries

An implementation request authorizes known isolated tests and correction of change-caused failures. Do not ask again only because another skill lists the same check.

Repository adapter tests use isolated SQLite schemas where configured. Deployment-script tests create temporary fixtures and fake external commands. Confirm the selected test setup before relying on that isolation.

Run Bruno and other service-backed tests only against identified, prepared targets within existing authority. A local command name does not establish a disposable target. An approved migration draft does not authorize applying it.

## Available commands

```sh
vendor/bin/phpunit <path>
composer phpstan
composer complexity
composer cs-check
composer docs:check-links
composer reports:check
php bin/composer-supply-chain.php check <isolated-vendor-directory>
composer test
```

`composer test` runs PHPUnit, writes JUnit and Clover XML, requires 100% executable-line coverage, then runs deployment checks.

The coverage gate reads `clover.xml` through `bin/check-coverage.php`. It fails when one executable source line is uncovered.

PHPStan, cyclomatic complexity, and PHPCS check `src`, `tests`, `config`, `public`, quality tools under `bin`, and `resources/database`.

API validation commands check source and merged OpenAPI schemas and endpoint tags.
`ApiDefinitionParityTest` checks all 52 documented operations, source/merged equality, and Bruno response assertions.
`ApiResponseAssertions` compares executed responses with source and merged schemas for keys, shapes, types, enums, and media types.
Identity `ModuleRoutingTest` checks module registration, handler namespaces, and route names against OpenAPI tags and operation IDs.
It verifies missing-token and cross-realm rejection on all 18 protected routes through the actual group middleware.
The identity HTTP suite covers public login, contact verification, authenticated flows, and anonymous preflight across both identity APIs.
ExampleApi tests cover shared User registration, contact verification, login, identity reads, token revocation, logout, and Account closure.
All twelve protected ExampleApi routes reject missing, invalid, ADMIN, revoked, and expired tokens before bus dispatch. Authentication dependency failures return 503.
Example detail reads retain API-key protection without checking bearer credentials.
Admin contact resend tests verify cooldown, context rejection, notification delivery, and confirmation without granting approval or changing Account ownership.
Account closure tests reject existing tokens and new login in both realms. Revocation tests cover User logout and Admin token revocation while preserving another Admin session.
Identity health tests execute both API route maps. Liveness skips dependency checks. Readiness returns 200 or a safe 503 response through deterministic dependency doubles.
`tests/Tools/OpenApiTagsTest.php` verifies valid tags, invalid tags, referenced path files, and invalid files with isolated fixtures.

Identity adapter tests cover retry limits, rollback, manager disposal, managed-record locks, stale revisions, and generated projection validation.
User email tests cover registration without email proof, stored-email verification after login, resend, expiry, and Account isolation.
Ownership state tests persist and reload both boolean values through `ownershipVerified`. Admin and seeder checks retain verified ownership.
Registration tests cover all four policy combinations in UserApi and ExampleApi, required and optional evidence, and context omission.
Compatibility tests preserve legacy authentication decisions and existing sessions after stricter configuration. User acceptance cannot authorize Admin login.
Identity state tests cover exact JSON serialization, nullable fields, missing fields, invalid types, and unknown fields.
Production Doctrine mapping tests verify typed replacement, fresh hydration, unchanged earlier snapshots, and rejection before domain use.
Contact tests preserve authoritative column values when JSON contains duplicate snapshot fields.
Credential tests cover scalar password-hash persistence, replacement, and closed-Account read and write rejection.
Schema parity tests require `password_hash VARCHAR(255) NOT NULL` and reject the former `credentials` column.
First-Admin tests run without an operator history table and retain invitation and approval evidence in Admin state.
They cover preview, separate evidence, repeated approval, additional and removed Admin rows, and bootstrap rollback.
Native MySQL tests verify fresh installation without operator history, first-Admin approval, and competing setup lock timeouts.
SQLite tests verify transaction rollback. Redis doubles verify commands, key scope, expiry arguments, and response validation.
Native RedisJSON tests verify token and session objects, key-level TTL, automatic removal, unchanged claims, and existing string-record reads.
They also verify duplicate-token and duplicate-session rejection without partial records or lifetime renewal.
Restricted Redis test users reject `JSON.SET` or `EXPIRE` to verify cleanup after a failed activation.
Production Doctrine metadata and the MySQL platform verify generated lock queries.
Native MySQL and Redis tests verify engine locking and Lua execution on prepared targets.

`JwtContractTest` checks emitted claim names and the separate actor and Account meanings.
Native MySQL tests cover Turkish UCA comparison and NO PAD uniqueness, error 1213 rollback, and active-mobile uniqueness and reuse after Account closure.
`RedisVerificationLimiterTest` executes the contact cooldown, contact/IP send budgets, and confirmation reservation scripts on a prepared Redis target.
`MySqlContactVerificationTest` checks single proof consumption and deadlines after observed contact lock waits with separate PHP processes.
`RedisRecoveryTest` uses fixture-owned `DUMP` and `RESTORE` records to check loss, partial recovery, reconnection, and current Account closure.
The Redis recovery Account gate uses isolated SQLite persistence. It does not prove MySQL/Redis atomicity or production replica durability.
`RedisFailoverTest` runs eight cases through a disposable primary, replica, and three Sentinels.
It verifies planned promotion, primary failure, stale revocations, and quorum loss through the production Redis connection provider.
The production RedisJSON library executes activation and validation scripts. Planned promotion preserves JSON object types and key expiry.
Use `bash bin/tests/redis-failover.sh` to run the dedicated cases.
Quality and release workflows run `bash bin/tests/redis-failover.sh composer test` with their separate MySQL and standalone Redis fixtures.
Ordinary PHPUnit skips failover cases unless the isolated runner supplies its generated project name and test flag.
See [identity acceptance](operations/identity-acceptance.md) for remaining deployment measurements and failover evidence.

`composer complexity` fails when one function or method has cyclomatic complexity greater than 12.

`composer run cs-fix -- <path>` forwards arguments to `phpcbf`. The wrapper maps exit code `1` to success and preserves other exit codes.

`composer docs:check-links` validates root documentation, `resources/docs`, and `resources/platform`. It also validates HTML fragment identifiers. It does not make network requests.
Use `php bin/check-documentation-links.php resources` to include ADRs, skill references, and other resource documents.
After ADR Markdown changes, run `node bin/build-adr-index.mjs`, then `node bin/build-adr-index.mjs --check` to verify the embedded copies.

Run `vendor/bin/phpunit tests/Tools --filter MessageViewer` for the local SMS and email viewer.
These tests cover parsing, bounded process reads, provider failures, HTTP responses, local-only access, and safe email previews.
They use isolated PHP child processes and HTTP client doubles. They send no notifications.
Verify browser rendering, filtering, refresh, and email previews against existing MiniStack captures after UI changes.

Run `vendor/bin/phpunit tests/Tools/DevelopmentCommandsTest.php` for the shared `bin/dev` environment check.
These tests cover exact `dev` selection, process precedence over `.env`, rejected modes, missing values, and malformed settings.
They use temporary project fixtures and stop allowed housekeeping at a command stub before any maintenance work.
The delayed command regression verifies a fixed assertion total regardless of process duration.
Keep timeout assertions outside polling loops so process timing cannot change generated report metrics.

Redis service fixtures use `Tests\Support\Services\RedisAuthentication` for optional password authentication.
The helper records one assertion with or without a password. It skips AUTH only when the configured password is empty.
Run `vendor/bin/phpunit tests/Tools/Services/RedisAuthenticationTest.php` for assertion-count, rejected-credential, and authentication-error regressions.
Keep fixture assertion totals independent of environment options as well as polling duration.

For agent instruction changes, include root and context files explicitly because the default targets omit them:

```sh
php bin/check-documentation-links.php AGENTS.md resources/platform resources/docs src/Backendbase/Domain .claude/agents
```

Check shared context routing and direct `AGENTS.md` references for both clients. Do not add client-specific instruction copies.
When moving instructions, compare the original and destination rules after accounting for headings and relative links.

`composer reports:update` reads JUnit and Clover XML. It updates marked metrics in the canonical engineering quality report. `composer reports:check` fails when committed metrics are stale.
Generate fresh test artifacts before updating metrics. Record skipped service cases and a failed coverage gate explicitly. Keep dated service verification separate from current local metrics.

GitHub Actions provides quality gates, security checks, and release-artifact workflows under `.github/workflows`. Each action reference uses an immutable commit SHA. Rector has no project configuration or direct Composer package.
The security workflow runs all identity operations over HTTP and separate authenticated ZAP scans for both realms.
The HTTP sequence verifies seeded operator login and protected identity access in both realms before lifecycle changes, including runs without scanning.
It uses the production entry point and native adapters with a local contact-notification sink.
See [identity security checks](operations/identity-security-checks.md) for prepared targets, scope, exclusions, and pinned scan inputs.
Deployment fixtures cover explicit fresh installation. Native MySQL tests reject existing tables, views, routines, and events.

The Composer supply-chain workflow runs each week and for dependency changes. It copies reviewed Composer public keys into an isolated Composer home before it diagnoses Composer 2.10.3. It disables plugins and scripts during review, audits the lock file, and checks the committed CycloneDX SBOM and package-content digests.

`bin/dev/housekeeping.sh` requires `BACKENDBASE_ENV=dev` before any maintenance work. It clears caches, fixes source style, generates ExampleApi, UserApi, and SystemAdminApi OpenAPI documents, and runs `composer update`. It then runs `composer update --lock --no-install --no-plugins --no-scripts --no-interaction` before isolated validation and audit. It installs the updated lock without plugins or scripts, then regenerates and checks both evidence files. Review the lock and evidence diffs before committing.

`bin/validate-agent-memory.sh` checks the local `.agents/memory` directories, templates, required fields, and orchestration rules. Run it from the repository root when that local memory structure exists. It does not validate application behavior.

Generate supply-chain evidence only from a new isolated install with `--no-plugins --no-scripts`. Review every lock, SBOM, and digest change before approval. A digest detects changed content. It does not establish that new content is safe.
Verify pristine packages before trusted install transformations. AWS service pruning and PHPCS configuration change the working vendor tree.
A later check against that transformed tree cannot substitute for the isolated-install check.

Basis: `resources/docs/10-testing-and-quality.html`.

HTTP replay tests under `tests/Infrastructure/Inbound/Idempotency` cover login, registration, contact delivery, and revoked authentication.
Adapter tests cover request matching, parallel attempts, encryption, storage failure, expiry, and cleanup.
`IdempotencyContractTest` checks required write headers, runtime rejection, CORS, and maintained Bruno headers.
Native MySQL replay checks remain opt-in and require a prepared target.
`MySqlHttpReplayTest` verifies HTTP replay, conflicts, encryption, expiry, cleanup, and unresolved claims through independent MySQL connections.
See [HTTP idempotency](operations/http-idempotency.md).
