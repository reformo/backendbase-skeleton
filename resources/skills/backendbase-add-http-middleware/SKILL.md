---
name: backendbase-add-http-middleware
description: Add or change PSR-15 HTTP middleware in a Backendbase-style API, including order, request attributes, failures, and tests. Do not use only to apply existing authentication middleware to a route.
---

# Add HTTP middleware

## Outcome

Implement one transport-boundary policy with explicit scope, correct middleware order, stable failure behavior, and focused pass, reject, and bypass tests.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Discover the actual API surface, route registry, middleware file, OpenAPI root, CORS configuration, and Bruno collection. Do not assume a fixed API list.
3. Inspect Composer autoloading, namespaces, architecture layers, container definitions, test layout, framework middleware order, and the nearest middleware.
4. Trace every request attribute and header the middleware consumes or produces, including the earlier component that supplies each value.
5. Resolve API-wide or route-level scope, public bypasses, OPTIONS behavior, failure status, response schema, and contract impact.
6. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).

Do not add a middleware or public bypass without a requested transport policy.

## Target-project adaptation

Use the target PSR interfaces, framework stack semantics, container, attribute names, header names, error response, and route inventory. Never copy Backendbase public paths, API keys, source IDs, hosts, or environment values blindly.

## Workflow

1. Define the trust boundary, inputs, output attributes, bypasses, and failure contract.
2. Implement the smallest PSR-15 middleware that enforces that policy.
3. Validate external values before constructing typed objects or calling dependencies.
4. Register it at the narrowest correct scope. Derive execution order from the target framework rather than source order alone.
5. Ensure every context attribute exists before a downstream middleware reads it.
6. Align runtime enforcement and OpenAPI for every required header and failure response. Include configured CORS and maintained Bruno coverage when applicable.
7. Test allow, deny, malformed, bypass, OPTIONS, attributes, execution order, and actual route protection.
8. Regenerate the merged OpenAPI document when the contract changed, validate source and generated documents, and review the generated diff.
9. Perform a semantic contract audit after schema validation.
10. Update related platform documentation.

## Backendbase invariants

- Middleware owns transport policy, not business decisions.
- In an unmodified Backendbase API, API context attributes exist before `ValidateApiKey` reads them.
- Slim executes added middleware last-in, first-out; registration and execution order need an integration test.
- Public bypasses correspond to actual routes and have an explicit reason.
- Failure status and body match OpenAPI.
- When CORS is configured, it permits every documented request header.
- Secrets and full authentication payloads are not logged.

## Verification

```sh
vendor/bin/phpunit tests/Shared/Http/Middleware/{MiddlewareTest}.php
vendor/bin/phpunit tests/Shared/Http/Middleware
vendor/bin/phpunit tests/Infrastructure/UseCase/{ApiName}/ModuleRoutingTest.php
vendor/bin/php-openapi validate resources/api-docs/{api-slug}/{root-spec}.yml
composer run generate-{api-slug}-spec
vendor/bin/php-openapi validate public/{api-slug}/docs/{api-slug}-merged.yml
git diff -- public/{api-slug}/docs/{api-slug}-merged.yml
composer phpstan
composer cs-check
```

Validate the merged OpenAPI document when the public contract changes. Compare runtime and OpenAPI semantically. Include configured CORS and maintained Bruno coverage when applicable.

## Completion report

Report scope, order, consumed and produced attributes, bypasses, failure contract, tests, and skipped checks.
