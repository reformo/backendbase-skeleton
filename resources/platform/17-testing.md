# Testing and Quality

Use the smallest check that proves the change. Expand checks as risk increases.

## Test ownership

- Keep movable context tests under `src/Backendbase/Domain/*/Tests`.
- Keep shared, infrastructure, functional, and architecture tests under `tests`.
- Use memory adapters for fast in-process lifecycle tests.
- Use production Doctrine metadata and isolated schemas for repository tests.
- Use deterministic doubles at network and provider boundaries.
- Keep invalid input, rollback, retry, mapping, and not-found paths explicit.

## Verification order

1. Run the changed test file.
2. Run the owning directory.
3. Run related database, API, queue, or integration tests.
4. Validate OpenAPI when the public contract changes.
5. Run PHPStan level 8.
6. Run PHPCS.
7. Run the full suite for broad changes.
8. Run Bruno only against a prepared running API.

```sh
vendor/bin/phpunit <path>
composer phpstan
composer cs-check
composer test
```

`composer test` runs PHPUnit with coverage, then the deployment shell-script checks.

The repository has no configured continuous integration pipeline. Rector has no project configuration or direct Composer package.

Basis: `resources/docs/10-testing-and-quality.html`.
