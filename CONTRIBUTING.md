# Contributing to Backendbase Core

Thank you for contributing to Backendbase Core.

## Before You Start

- Use PHP 8.5 with the extensions listed in `composer.json`.
- Install Composer dependencies with `composer install`.
- Use `docker compose up -d` when your change needs MySQL, Redis, or RabbitMQ.
- Read `resources/docs/project.md` for the architecture and project workflow.
- Report security issues through the private process in `SECURITY.md`.

## Make a Change

1. Create a focused branch from `trunk`.
2. Keep the change limited to one purpose.
3. Preserve the dependency rules in `resources/docs/project.md`.
4. Add or update tests for changed behavior.
5. Update OpenAPI and Bruno cases when an API contract changes.
6. Update the `Unreleased` section of `CHANGELOG.md` in every commit.

Include the changelog entry in the same commit as the related code or documentation change.

Do not add database structures that are not part of the requested change. Review every generated migration before you run it.

## Verify a Change

Run the smallest relevant PHPUnit test first. Then run the full local quality checks:

```sh
composer test
composer phpstan
composer cs-check
composer docs:check-links
composer reports:check
composer audit --locked
composer validate --strict
vendor/bin/php-openapi validate public/example-api/docs/example-api-merged.yml
```

Run `composer reports:update` after test or inventory changes. Commit the updated report with the related change.

Run the Bruno collection when the change affects a running API:

```sh
bin/bruno example-api local
```

## Propose a Change

Include this information in the change description:

- The problem and its effect.
- The selected solution and important tradeoffs.
- The tests and checks that you ran.
- Any migration, configuration, API, queue, or compatibility effect.

Keep commits clear and limited to the proposed change. Do not include generated caches, local environment files, credentials, or unrelated formatting changes.
