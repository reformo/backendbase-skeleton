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
`ApiDefinitionParityTest` checks all 32 documented operations, source/merged equality, and Bruno response assertions.
`ApiResponseAssertions` compares executed responses with source and merged schemas for keys, shapes, types, enums, and media types.
`tests/Tools/OpenApiTagsTest.php` verifies valid tags, invalid tags, referenced path files, and invalid files with isolated fixtures.

Identity adapter tests cover retry limits, rollback, manager disposal, managed-record locks, stale revisions, and generated projection validation.
SQLite tests verify transaction rollback. Redis doubles verify commands, key scope, expiry arguments, and response validation.
Production Doctrine metadata and the MySQL platform verify generated lock queries.
Native MySQL and Redis tests verify engine locking and Lua execution on prepared targets.

`JwtContractTest` checks emitted claim names and the separate actor and Account meanings.
Native MySQL tests cover Turkish UCA comparison and NO PAD uniqueness, error 1213 rollback, and active-login restoration conflicts.
`MySqlRelationshipRaceTest` checks challenge creation against User removal with separate PHP processes and observed lock waits.
`RedisRecoveryTest` uses fixture-owned `DUMP` and `RESTORE` records to check loss, partial recovery, reconnection, and current Account closure.
The Redis recovery Account gate uses isolated SQLite persistence. It does not prove MySQL/Redis atomicity or production replica durability.
`RedisFailoverTest` runs eight cases through a disposable primary, replica, and three Sentinels.
It verifies planned promotion, primary failure, stale revocations, and quorum loss through the production Redis connection provider.
Use `bash bin/tests/redis-failover.sh` to run the dedicated cases.
Quality and release workflows run `bash bin/tests/redis-failover.sh composer test` with their separate MySQL and standalone Redis fixtures.
Ordinary PHPUnit skips failover cases unless the isolated runner supplies its generated project name and test flag.
See [identity acceptance](operations/identity-acceptance.md) for remaining deployment measurements and failover evidence.

`composer complexity` fails when one function or method has cyclomatic complexity greater than 12.

`composer run cs-fix -- <path>` forwards arguments to `phpcbf`. The wrapper maps exit code `1` to success and preserves other exit codes.

`composer docs:check-links` validates local HTML and Markdown targets. It also validates HTML fragment identifiers. It does not make network requests.

`composer reports:update` reads JUnit and Clover XML. It updates marked metrics in the canonical engineering quality report. `composer reports:check` fails when committed metrics are stale.

GitHub Actions provides quality gates, security checks, and release-artifact workflows under `.github/workflows`. Each action reference uses an immutable commit SHA. Rector has no project configuration or direct Composer package.

The Composer supply-chain workflow runs each week and for dependency changes. It copies reviewed Composer public keys into an isolated Composer home before it diagnoses Composer 2.10.3. It disables plugins and scripts during review, audits the lock file, and checks the committed CycloneDX SBOM and package-content digests.

`bin/dev/housekeeping.sh` clears caches, fixes source style, generates ExampleApi, UserApi, and SystemAdminApi OpenAPI documents, and runs `composer update`. It then runs `composer update --lock --no-install --no-plugins --no-scripts --no-interaction` before isolated validation and audit. It installs the updated lock without plugins or scripts, then regenerates and checks both evidence files. Review the lock and evidence diffs before committing.

`bin/validate-agent-memory.sh` checks the local `.agents/memory` directories, templates, required fields, and orchestration rules. Run it from the repository root when that local memory structure exists. It does not validate application behavior.

Generate supply-chain evidence only from a new isolated install with `--no-plugins --no-scripts`. Review every lock, SBOM, and digest change before approval. A digest detects changed content. It does not establish that new content is safe.

Basis: `resources/docs/10-testing-and-quality.html`.
