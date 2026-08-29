# Testing and Quality

Use the smallest check that proves the change. Expand checks as risk increases.

## Test ownership

- Keep movable context tests under `src/Backendbase/Domain/*/Tests`.
- Keep shared, infrastructure, functional, and architecture tests under `tests`.
- Keep operational-tool tests under `tests/Tools`.
- Use memory adapters for fast in-process lifecycle tests.
- Use production Doctrine metadata and isolated schemas for repository tests.
- Run the same behavioral repository contract suite against every adapter for one port.
- Keep adapter-specific tests for storage details such as mapping and rollback.
- Use deterministic doubles at network and provider boundaries.
- Use `docker compose --profile integration up -d` for opt-in S3, SQS, and SNS adapter integration tests.
- Create and remove emulator resources in each integration test setup. Do not add the emulator to the default suite.
- Keep invalid input, rollback, retry, mapping, and not-found paths explicit.
- Keep dependency-direction tests separate for Application, Shared core, inbound adapters, and outbound adapters.
- Verify CQRS and domain-listener attribute targets for shape, context, interface, and production-container resolution.

## Verification order

1. Run the changed test file.
2. Run the owning directory.
3. Run related database, API, queue, or integration tests.
4. Validate OpenAPI when the public contract changes.
5. Run PHPStan level 8.
6. Run the cyclomatic-complexity check.
7. Run PHPCS.
8. Validate documentation links.
9. Verify generated report metrics.
10. Verify supply-chain evidence when Composer metadata changes.
11. Run the full suite for broad changes.
12. Run Bruno only against a prepared running API.

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

`composer complexity` fails when one function or method has cyclomatic complexity greater than 15.

`composer docs:check-links` validates local HTML and Markdown targets. It also validates HTML fragment identifiers. It does not make network requests.

`composer reports:update` reads JUnit and Clover XML. It updates marked metrics in the canonical engineering quality report. `composer reports:check` fails when committed metrics are stale.

GitHub Actions provides quality gates, security checks, and release-artifact workflows under `.github/workflows`. Each action reference uses an immutable commit SHA. Rector has no project configuration or direct Composer package.

The Composer supply-chain workflow runs each week and for dependency changes. It copies reviewed Composer public keys into an isolated Composer home before it diagnoses Composer 2.10.3. It disables plugins and scripts during review, audits the lock file, and checks the committed CycloneDX SBOM and package-content digests.

Generate supply-chain evidence only from a new isolated install with `--no-plugins --no-scripts`. Review every lock, SBOM, and digest change before approval. A digest detects changed content. It does not establish that new content is safe.

Basis: `resources/docs/10-testing-and-quality.html`.
