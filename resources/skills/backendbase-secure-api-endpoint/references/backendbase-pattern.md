# Backendbase endpoint security pattern

## Security layers

```text
API selection -> Slim route match -> API context -> API-key middleware
-> route-level bearer identity -> typed authorization context -> controller
-> command -> application-handler authorization -> side effects
```

This is the effective current Backendbase order. Slim routing is added after the API middleware registrations, so last-in, first-out execution matches the route before those middleware run. Recompute the order for a target framework instead of copying source registration order.

| Layer | Backendbase behavior | Target adaptation |
| --- | --- | --- |
| API key | Compares a configured API secret except explicit public paths | Header, lookup, public policy, failure status |
| JWT | HMAC SHA-256 with Base64 key; validates claims and Redis state | Target algorithm and revocation contract |
| Request identity | Adds user, timezone, and typed access-control attributes | Target typed identity context |
| Application authorization | Handler checks the named privilege before side effects | Target privilege and role model |
| OpenAPI | Write operations declare API key and bearer together | Exact target scheme semantics |

## Policy matrix

| Policy | Runtime requirement | OpenAPI requirement |
| --- | --- | --- |
| Anonymous | Every applicable security layer intentionally bypasses the route | `security: []` |
| API key only | API-wide key middleware validates the request | One API-key scheme |
| Authenticated | API key and bearer middleware both validate | Both schemes in one requirement |
| Privileged | Authentication plus an explicit named ACL check | Authentication schemes plus documented `403` |

Two schemes in one OpenAPI requirement are logical AND. Two separate requirement objects are logical OR. Do not call an API-key-only route public.

A protected route group adds the established `AuthorizationMiddleware`. The controller passes the typed context into the command. The application handler keeps the decision explicit:

```php
$command->accessControl()->isAllowed(self::REQUIRED_PRIVILEGE);
```

Prefer a typed identity or authorization service when the target supplies one. Do not make authorization decisions from unvalidated token claims.

`Acl::isAllowed()` throws the current forbidden problem when the named privilege is absent. Invoke it at the application handler boundary before repository, transaction, or external-service work. Do not inspect raw privilege arrays.

## Route-level proof

Test security through the actual route stack. Cover the applicable cases:

- Anonymous access to an explicitly public operation.
- Missing or invalid API key.
- Missing, malformed, expired, or revoked bearer token.
- Authenticated identity without the named privilege.
- Administrative or explicit privilege success.
- Invalid security-related headers, including timezone, when the contract requires validation.

Compare each result with the OpenAPI status and problem body. Include configured CORS policy and maintained Bruno requests when applicable. Middleware and ACL unit tests remain useful, but they do not prove that a route uses them.

## Current source limitations

- Missing, malformed, expired, and invalid bearer values return `400`, not the intended `401`.
- API-key failures return `400`, while OpenAPI lists `401` and `403`.
- The authentication action uses fixed demonstration user data and does not verify the submitted password.
- The timezone header is passed directly to `DateTimeZone`; invalid values need boundary handling.
- Existing read routes omit bearer middleware but still require the global API key outside configured bypasses.
- JWT validation exceptions are returned with their messages. Do not copy provider or internal exception detail into a public response.
- Do not encode these gaps as reusable policy.

## Exact source provenance

- `src/Backendbase/Domain/IdentityAndAccess/Adapters/Authentication/Jwt.php`
- `src/Backendbase/Domain/IdentityAndAccess/Adapters/Authentication/JwtTokenCodec.php`
- `src/Backendbase/Domain/IdentityAndAccess/Adapters/Authentication/JwtTokenConfiguration.php`
- `src/Backendbase/Domain/IdentityAndAccess/Adapters/Authentication/JwtAuthorizationStore.php`
- `src/Backendbase/Domain/IdentityAndAccess/Contracts/TokenIssuer.php`
- `src/Backendbase/Domain/IdentityAndAccess/Contracts/TokenValidator.php`
- `src/Backendbase/Domain/IdentityAndAccess/Contracts/AuthorizationStore.php`
- `src/Backendbase/Domain/IdentityAndAccess/Adapters/Http/AuthorizationMiddleware.php`
- `src/Backendbase/Domain/IdentityAndAccess/Authorization/Acl.php`
- `src/Backendbase/Shared/Authorization/AccessControl.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Application/CommandHandlers/`
- `src/Backendbase/Shared/Http/Middleware/ValidateApiKey.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/middleware.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example/ModuleConfig.php`
- `config/example-api/jwt.global.php`
- `config/example-api/http-headers.global.php`
- `resources/api-docs/example-api/example-openapi.yml`
- `resources/api-docs/example-api/example/`
- `resources/bruno/example-api/example/`
- `tests/Domain/IdentityAndAccess/Adapters/Authentication/JwtTest.php`
- `tests/Domain/IdentityAndAccess/Adapters/Http/AuthorizationMiddlewareTest.php`
- `tests/Domain/IdentityAndAccess/Authorization/AclTest.php`
- `tests/Shared/Http/Middleware/ValidateApiKeyTest.php`
- `tests/Infrastructure/UseCase/ExampleApi/ModuleRoutingTest.php`
- `resources/docs/project.md`
- `resources/docs/8-authentication-and-authorization.html`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/14-security.md`
- `resources/platform/16-errors-observability.md`

These files define the current mechanism, not target credentials or policy names.
