# Authentication and Authorization

Authentication validates token identity and state. Authorization decides whether that identity can perform an operation.

## JWT

- Tokens use HMAC SHA-256 and a Base64 signing key.
- Validate signature, time, issuer, audience, token identifier, and Redis state.
- Redis enables immediate token revocation.
- The default alias is `USER`.
- The default issuer is `backendbase-api`.
- The default lifetime is 24 hours.

HTTP inputs depend on the `TokenIssuer` and `TokenValidator` application ports. The JWT adapter implements both ports.

`AuthorizationStore` isolates Redis-backed token state from token orchestration.

`POST /auth` validates a non-deleted account from `example_accounts`. It verifies the stored Argon2id password hash. It stores active privilege slugs in the JWT authorization state.

`AuthorizationMiddleware` adds `authorizedUserId`, `authorizedUserData`, `clientTimezone`, `Acl`, and `AccessControl` request attributes.

`Acl::isAllowed()` accepts a named privilege, `full-privileges`, or the `system-admin` role. Denial uses status 403.

Example write commands require `AccessControl`. Their application handlers check these privileges before side effects:

- `example.add`
- `example.change`
- `example.remove`

Keep privilege checks at the application handler boundary. Do not make this decision only in an HTTP controller.

Protect new endpoints by default. Make public policy explicit in routing and OpenAPI.

ExampleApi middleware currently adds `ValidateApiKey`. It validates `Backendbase-Api-Key` outside configured public paths and returns status 400 on failure.

Invalid credentials return status 401. Bearer failures still return status 400 instead of 401.

Basis: `resources/docs/5-use-case-api.html`, `resources/docs/7-env-and-config.html`, `resources/docs/8-authentication-and-authorization.html`.
