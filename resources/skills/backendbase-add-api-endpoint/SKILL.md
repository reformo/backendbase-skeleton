---
name: backendbase-add-api-endpoint
description: Implement one endpoint in an existing Backendbase-style API module, including boundary validation, bus dispatch, response mapping, contract alignment, and tests. Do not use for a new API, module, or contract-only edit.
---

# Add an API endpoint

## Outcome

Implement one requested HTTP operation that validates external input, invokes the correct use case, returns the documented response, and has focused failure-path tests.

## Required discovery

1. Read all applicable `AGENTS.md` files.
2. Discover the actual API surfaces from the target selector, use-case directories, route registries, OpenAPI roots, and Bruno collections. Do not assume a fixed API list.
3. Inspect Composer autoloading, namespaces, layer boundaries, container definitions, test layout, and the nearest endpoint of the same method and response type.
4. Confirm the owning API and module already exist and identify every explicitly affected API surface.
5. Resolve the complete command or query use case, authorization policy, input rules, empty or not-found behavior, status, headers, and response fields.
6. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).

Ask before inventing a public route, resource contract, or domain behavior.

## Target-project adaptation

Use the target framework, request types, namespace, command and query buses, response classes, exception contract, and test helpers. Never copy Example resource fields, slugs, headers, secrets, hosts, or fixtures blindly.

## Workflow

1. Draft the method, path, operation name, security, request contract, response contract, and errors in the authoritative OpenAPI source.
2. Reuse a complete typed command or query path. If it is absent, add its handler, ports, adapters, registration, and tests before endpoint wiring.
3. Do not create or change database structure unless the user explicitly requests that schema change.
4. Add or reuse a small boundary validator for path, query, header, and body data.
5. Sanitize where needed, then separately validate required state, types, formats, ranges, sizes, and allowed values before dispatch. Sanitization is not validation.
6. Construct the typed command or query from validated values and dispatch it through the target bus.
7. Avoid a controller query followed by a command when one command can own the operation. When a preflight query is unavoidable, treat it as non-atomic and make the command handler reload state and enforce current invariants.
8. Map the result to the exact documented response body, status, and headers.
9. Register the route, then verify its API-key, bearer, and ACL policy across the complete stack. In an unmodified Backendbase API, default a new consumer operation to API key plus bearer unless the explicit target policy selects API-key-only or anonymous access. Use `security: []` only for an explicitly public operation.
10. Reconcile the OpenAPI draft with the implemented route, middleware, validation, response, and errors. Update the affected request when the target maintains a Bruno collection.
11. Test valid mapping, invalid input, bus non-dispatch on failure, empty or not-found behavior, response semantics, and actual route middleware.
12. Verify runtime, OpenAPI, and tests agree on each request header and security requirement. Include maintained Bruno coverage and configured CORS when applicable.

## Backendbase invariants

- In an unmodified Backendbase API, actions extend the shared `Action` base. Other projects must use their established HTTP and error boundary.
- Controllers do not access Doctrine, SQL, aggregate persistence, outbox, or queues.
- Invalid input never reaches a command or query bus.
- A boundary failure never dispatches a command or query. The command handler owns authoritative missing-state and invariant decisions for a write.
- Request and response attributes follow the active project convention and match OpenAPI exactly.
- In an unmodified Backendbase API, new consumer operations require API key plus bearer by default. API-key-only or anonymous access requires an explicit target policy.
- API-key validation, bearer authentication, and ACL authorization are separate decisions.
- Do not leave a new command or query without a resolvable handler and complete application-path tests.
- Known problem exceptions use the shared problem-details response path.

## Verification

```sh
vendor/bin/phpunit tests/Infrastructure/UseCase/{ApiName}/{EndpointTest}.php
vendor/bin/phpunit tests/Infrastructure/UseCase/{ApiName}
composer run generate-{api-slug}-spec
vendor/bin/php-openapi validate public/{api-slug}/docs/{api-slug}-merged.yml
composer phpstan
composer cs-check
```

For a collection response, test empty results, pagination bounds, defaults, and maximum size according to the contract. Run Bruno only when the API and its dependencies are prepared.

## Completion report

Report the method and path, dispatched contract, security policy, response, tests, contract status, and skipped checks.
