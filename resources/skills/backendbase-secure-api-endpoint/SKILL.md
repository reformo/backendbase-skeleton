---
name: backendbase-secure-api-endpoint
description: Protect an existing Backendbase-style API operation with the established API-key, JWT bearer, and ACL mechanisms. Do not use to create a new identity provider, credential flow, or generic middleware.
---

# Secure an API endpoint

## Outcome

Apply an explicit public or protected policy to one operation. Keep routing, authorization context, application decisions, OpenAPI security, and failure tests aligned.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read applicable `AGENTS.md` files.
2. Inspect the affected route, authentication stack, application privilege checks, public contract, and focused security tests.
3. Discover the owning API and trace source selection, API-context setup, API-key middleware, bearer validation, JWT settings and state, request attributes, ACL usage, CORS, and OpenAPI schemes.
4. Resolve the exact API-key rule, identity requirement, named privilege, public exception if any, expected claims, and `401` versus `403` behavior.
5. Confirm how the real route stack proves each layer and where runtime currently differs from the intended contract.
6. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Ask before making an endpoint public or changing token and privilege semantics.

## Target-project adaptation

Use the target identity model, claims, issuer, audience, key storage, revocation store, roles, privileges, headers, and status contract. Never copy Backendbase signing data, API keys, token values, user fixtures, or hosts.

## Workflow

1. State whether the operation is anonymous, API-key-only, bearer-authenticated, or privilege-protected. For a new operation in an unmodified Backendbase consumer API, select API key plus bearer unless an explicit policy requires another mode.
2. Apply only the API-key and bearer layers selected in step 1, at the narrowest correct scope.
3. Pass the established typed authorization context through the input contract for each named privilege.
4. Enforce the named privilege at the application handler boundary before protected work.
5. Keep credential parsing and token validation outside controllers.
6. Use `security: []` only when all applicable runtime security layers intentionally permit anonymous access.
7. Declare identical API-key and bearer requirements and failure responses in OpenAPI.
8. Align every security-related header across runtime validation and OpenAPI. Also align configured CORS and maintained Bruno coverage when applicable.
9. Regenerate the merged OpenAPI document, validate source and generated contracts, and review the generated diff.
10. Test missing, malformed, expired, revoked, forbidden, and allowed paths as applicable.
11. Exercise the real route and middleware stack. Unit tests of middleware or ACL alone do not prove route protection.

## Backendbase invariants

- New consumer endpoints are protected by default.
- In an unmodified Backendbase consumer API, a new operation requires API key plus bearer by default. API-key-only or anonymous access requires an explicit policy.
- In an unmodified Backendbase API, `AuthorizationMiddleware` supplies identity data and typed `AccessControl`.
- In an unmodified Backendbase API, commands and queries that need a named privilege require `AccessControl`.
- In an unmodified Backendbase API, application handlers ask `AccessControl` for the named privilege before protected work.
- Other projects must use their equivalent typed authorization service at the discovered application boundary.
- API-key validation, bearer authentication, and ACL authorization are independent policy layers.
- An API-key-only operation is protected; an anonymous operation uses `security: []` and a matching runtime bypass.
- `full-privileges` and `system-admin` are current Backendbase policy names, not portable defaults. Do not create equivalent bypasses unless the target policy requires them.
- JWT validation includes signature, time, issuer, audience, token ID, and Redis state.
- A missing identity is `401`; a valid identity without permission is `403` in the intended public contract.
- Secrets, tokens, and personal identity data are not logged.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
vendor/bin/phpunit src/Backendbase/Domain/IdentityAndAccess/Tests
vendor/bin/phpunit tests/Infrastructure/UseCase/{ApiName}/Middleware
vendor/bin/phpunit tests/Infrastructure/Adapters/Http/Middleware/ValidateApiKeyTest.php
vendor/bin/phpunit tests/Infrastructure/UseCase/{ApiName}/ModuleRoutingTest.php
vendor/bin/php-openapi validate resources/api-docs/{api-slug}/{root-spec}.yml
composer run generate-{api-slug}-spec
vendor/bin/php-openapi validate public/{api-slug}/docs/{api-slug}-merged.yml
git diff -- public/{api-slug}/docs/{api-slug}-merged.yml
composer phpstan
composer complexity
composer cs-check
```

## Completion report

Report the operation, each applied security layer, privilege, request attributes used, status contract, route-level evidence, and blocked required checks.
