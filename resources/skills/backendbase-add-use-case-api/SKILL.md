---
name: backendbase-add-use-case-api
description: Scaffold a distinct Backendbase-style consumer Use Case API with selection, configuration, routes, OpenAPI, Bruno, and tests. Use for a new API surface; do not use for a module or endpoint inside an existing API.
---

# Add a Use Case API

## Outcome

Create one complete API surface that boots, resolves configuration, registers middleware and routes, publishes a valid contract, and has focused tests.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files before editing.
2. Inspect the API selector, nearest API bootstrap, route and container registration, contract tooling, tests, and release packaging.
3. Find the nearest working API and trace its selector, bootstrap, middleware order, routes, configuration, generated documentation, and tests.
4. Resolve the requested API class name, slug, source identifier, base path, public operations, security policy, and health dependencies.
5. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Stop and ask when a missing public surface or security decision would materially change the API.

## Target-project adaptation

Use the target project's namespace, paths, headers, environment keys, hosts, credentials strategy, and fixture policy. Treat Backendbase names as role examples only. Never copy `Backendbase\`, `ExampleApi`, `example-api`, `Backendbase-Api-Key`, tokens, secrets, hosts, or fixture identifiers without an explicit target match.

## Workflow

1. Define the API name, slug, selector input, public base path, and ownership.
2. Add the selector mapping and prove both valid and invalid selection.
3. Create API middleware, routes, module registry, root handlers, and configuration roots.
4. Add only the public, authentication, and health operations requested by the user.
5. Add editable OpenAPI sources, generated-document wiring, and generation commands.
6. Add a Bruno collection only when executable API coverage is in scope.
7. Update container, cache, deployment, and documentation references that enumerate APIs.
8. Add focused bootstrap, route, middleware, and controller tests.

## Backendbase invariants

- HTTP adapters live under `Infrastructure/UseCase`, outside bounded contexts.
- Current API selection uses `X-Source-Id` through `UseCaseTarget`.
- API class name, slug, selector, config, cache, OpenAPI, public docs, and Bruno names stay aligned.
- `ModuleRoutes` registers every module explicitly.
- New consumer routes are protected unless the requested public use case is explicit.
- Controllers adapt HTTP to commands or queries. They do not access persistence or brokers.
- Every OpenAPI operation references the four shared request headers.
- Generated OpenAPI files are outputs. Do not hand-edit them.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Choose the commands that prove the affected bootstrap, routing, and contract behavior:

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/Http/Bootstrap
vendor/bin/phpunit tests/Infrastructure/UseCase/{ApiName}
composer run generate-{api-slug}-spec
vendor/bin/php-openapi validate public/{api-slug}/docs/{api-slug}-merged.yml
composer phpstan
composer complexity
composer cs-check
```

Run Bruno only against a prepared service with safe credentials and data. Clear compiled configuration after config, route, or dependency changes.

## Completion report

Report the selected API identity, added roots, public and protected policy, contract generation result, tests run, and any blocked required check and its exact blocker.
