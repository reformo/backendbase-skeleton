---
name: backendbase-add-api-module
description: Add a route-prefix module to an existing Backendbase-style Use Case API and register its operations. Use for a new HTTP module; do not use for one endpoint or a separate API surface.
---

# Add an API module

## Outcome

Add one cohesive HTTP module whose route prefix, handlers, authorization groups, contract paths, and tests are registered in an existing API.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Discover the actual API surfaces from the selector, use-case directories, route registries, OpenAPI roots, and Bruno collections. Do not assume a fixed API list.
3. Inspect the owning API selector, nearest route module, registration, public contract, and focused tests.
4. Confirm the target API already exists and identify each explicitly affected API surface.
5. Resolve the module owner, route prefix, operations, route names, complete application paths, and public or protected policy.
6. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not create a new API or speculative endpoints to justify a module.

## Target-project adaptation

Use the target API namespace, routing framework, module interface, naming rules, container, and test helpers. Do not copy Example module names, paths, headers, hosts, credentials, or fixtures.

## Workflow

1. Define a stable module prefix and list only the requested operations.
2. Draft the matching OpenAPI paths, operation names, inputs, responses, and security policy.
3. Confirm that every handler can call a complete command or query application path before adding controller wiring.
4. Create the target module configuration using the project's route-module contract.
5. Register the module in the API's explicit module registry.
6. Group bearer-authenticated operations with the established bearer middleware. In an unmodified Backendbase API, use this as the default for new consumer operations unless the explicit target policy selects API-key-only or anonymous access. Keep API-key-only operations outside that group, and treat ACL policy as a separate layer.
7. Add or connect handlers without moving business logic into routing code.
8. Reconcile route placeholders, operation names, security, responses, and shared headers with OpenAPI and any configured CORS policy.
9. Regenerate the merged OpenAPI document, validate source and generated contracts, and review the generated diff.
10. Update affected requests when the target maintains a Bruno collection.
11. Add focused registry tests and route-level middleware tests.
12. Update API documentation that enumerates modules.

## Backendbase invariants

- In an unmodified Backendbase API, `ModuleConfig` implements `ModuleRoute`.
- In an unmodified Backendbase API, `ROUTE_KEY` and `routeKey()` identify the same prefix.
- In an unmodified Backendbase API, `ModuleRoutes` registers every module explicitly.
- Route names are unique and match the corresponding OpenAPI operation IDs.
- In an unmodified Backendbase API, a bearer-authenticated subgroup preserves the module base path and adds `AuthorizationMiddleware`.
- Routing code selects transport policy only. It does not contain domain decisions.
- Path placeholders and OpenAPI parameter names match exactly.
- An API-key-only operation is protected. Only an intentional anonymous operation uses `security: []`.
- For a new Backendbase consumer operation, API-key-only access is an explicit exception to the API-key-plus-bearer default.
- A route group that validates bearer identity does not replace an explicit ACL decision when a privilege is required.
- Adding a module does not authorize a new database schema or speculative application behavior.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit tests/Infrastructure/Inbound/{ApiName}/{ModuleRoutingTest}.php
# Use the owning directory instead when the change needs broader coverage.
vendor/bin/phpunit tests/Infrastructure/Inbound/{ApiName}
vendor/bin/php-openapi validate resources/api-docs/{api-slug}/{root-spec}.yml
composer run generate-{api-slug}-spec
vendor/bin/php-openapi validate public/{api-slug}/docs/{api-slug}-merged.yml
git diff -- public/{api-slug}/docs/{api-slug}-merged.yml
composer phpstan
composer complexity
composer cs-check
```

Update route-count assertions to the intended total. Do not weaken them only to make the test pass. Exercise the real route middleware because route counts and names do not prove protection.

## Completion report

Report the module prefix, registered operations, security grouping, contract files, tests, and blocked required checks.
