# HTTP API Adapter

An inbound API exposes application operations over HTTP. It owns request validation, routing, middleware, and response mapping.
Application handlers own use-case coordination. An inbound API must not own domain rules, persistence, or transaction control.

Use `inbound API` for an HTTP API surface and `inbound adapter` for the broader architectural category.
Console entry points and message handlers can also be inbound adapters. Keep `consumer` for message consumers and API clients.

## Runtime

Nginx API ingress limits each client IP to 30 requests per second with a burst allowance of 60 across all three APIs.
Gateway rejection returns HTTP 429 before PHP, authentication, and replay. Application verification quotas remain in code.
See [HTTP rate limiting](operations/http-rate-limiting.md) for configuration, trusted client addresses, and the internal readiness exception.

- All public entry points use `public/index.php`, which selects an API from `X-Source-Id` through `InboundApiTarget`.
- Invalid source selection returns HTTP `400` with `application/problem+json` and a matching Problem Details status before container creation.
- `public/example-api/index.php` assigns API name and slug variables that the shared bootstrap does not read. Its requests still require `X-Source-Id: example`.
- API middleware, routes, controllers, configuration, OpenAPI, and Bruno use matching class and slug names.
- `ModuleRoutes` must register each HTTP module explicitly.
- ExampleApi, UserApi, and SystemAdminApi each own their identity controllers and route registration. Their route policies select a fixed authentication realm.
- Group new endpoint controllers by OpenAPI tag inside their owning API. Use `Controllers/{TagName}/ModuleConfig.php` and `Controllers/{TagName}/Handlers/`.
- ExampleApi includes Authentication, Identity, and Example modules. UserApi and SystemAdminApi modules are Authentication, Identity, System, and SystemAdminApi Administration.
- Follow ExampleApi's module pattern: `ModuleConfig` implements `ModuleRoute`, declares `ROUTE_KEY`, and registers one handler per operation under `Handlers`.
- `ModuleRoutes` maps prefixes to module classes. `routes.php` creates their route groups. Route names match OpenAPI operation IDs.
- Use fluent route declarations in `ModuleConfig`, following ExampleApi. Chain the HTTP method, `setName()`, and endpoint-specific `add()` calls.
- Place routes with common middleware in `group()`. Attach shared middleware once to that group.
- Reusable identity input, dispatch, and policy helpers live under `Infrastructure/Adapters/Http/Identity`. Administration input belongs inside SystemAdminApi's Administration module.

Authentication, Identity, Administration, and protected Example groups define their realm once per group through `RealmPolicy`.
Identity, Administration, and protected Example operations use an empty-prefix subgroup. The subgroup adds `Idempotency`, `RealmAuthentication`, then its fixed `RealmPolicy`.
Slim runs the last added middleware first. The group policy supplies `expectedRealm` before authentication runs.
`RoutePolicy` supplies only the endpoint action. `ContactRoutePolicy` supplies only contact settings. Neither policy sets or replaces the group realm.
Endpoint action and contact policies run after group authentication. Public Authentication groups apply `RealmPolicy` and write replay through `Idempotency` and do not require a bearer token.
Anonymous authentication, health, and preflight routes remain outside protected groups.
Use non-static group callbacks because Slim binds them to the dependency container.

## Controller rules

- Business endpoint actions extend `Backendbase\Infrastructure\Adapters\Http\Actions\Action`.
- A transport-only system handler can be directly invokable when it needs no shared action flow. `Liveness` is the current exception.
- Read and validate PSR-7 input before constructing a command, query, or typed application-orchestrator input.
- For PATCH input, preserve omitted nullable fields and reject supplied values with invalid types before command construction.
- Invoke one application operation per endpoint. Normally dispatch one command or query. For a write response needing a resource representation, call one application orchestrator under [the CQRS result rules](04-cqrs.md#client-visible-write-results).
- For an identifier-only response, generate the identifier before a create command or reuse an existing resource's public identity. Return it after successful execution. Keep authoritative write lookup in the command handler.
- Keep command-then-read coordination in the application orchestrator. Document read consistency and failures after commit. Keep HTTP response mapping in the action.
- Pass typed `AccessControl` into a privileged command or query. Keep the named privilege decision in its application handler.
- Map the result to the documented response shape.
- Select the documented HTTP status and headers.
- Successful JSON bodies use descriptive resource or result keys, such as `user`, `admin`, or `challenge`.
- Do not use generic `data` wrappers or top-level `statusCode` fields.
- Pass the complete named response body to `ActionPayload`. It serializes that body without adding a wrapper.
- Choose response keys in the HTTP adapter, with singular names for resources and plural names for collections.
- Keep existing direct projections, health bodies, and empty responses in their documented shapes.
- Use `apiMetadata` for Example metadata. Serialize empty object maps as `{}` and empty collections as `[]`.
- Problem Details errors retain their integer `status` field. It must equal the HTTP response status.
- Slim exception titles use the standard HTTP reason phrase. Domain error titles describe the mapped problem type.
- Never access Doctrine, SQL, queues, or aggregate persistence directly.

Protect new inbound API endpoints with the existing API-key middleware by default. Declare anonymous access explicitly. Example operations require an API key. Every Example operation except `getExampleByCriteria` also requires a USER bearer token.

Example POST and PATCH read bounded raw JSON through `ExampleRequestBody`. The root and supplied non-null details must be objects.
The API preserves accepted text and detail values. It does not escape HTML, transform credential-like detail names, or derive image URLs.
Example types are `system` and `user`. Groups, keys, values, and target identifiers follow the context value constraints.

`X-Source-Id` selects `example`, `user`, or `system-admin`. The header does not grant access.
Realm endpoints use route-owned bearer authentication. ExampleApi reuses the USER realm and existing UserApi sessions. Its Authentication and Identity modules expose the same operations as UserApi.
ExampleApi CORS permits `Authorization` and `Backendbase-Api-Key`. Public authentication and health routes explicitly permit anonymous access.
Optional locale, timezone, and trace headers use documented defaults.
All three API CORS configurations permit `Accept-Language`, timezone, trace, and source headers.

Basis: `resources/docs/5-inbound-api.html`.

The bootstrap validates supplied trace UUIDs and timezone values before logger creation. Missing optional headers retain documented defaults.
JSON body limits run before parsing. Identity inputs require JSON objects, while token, session, and Account deletion require empty bodies.

Browser OPTIONS preflight can select a fixed inbound API from its path without an `X-Source-Id` value.
Ordinary requests still require the declared source header. Preflight does not invoke authentication or business handlers.

## Idempotent writes

All documented POST, PATCH, and DELETE operations require `Idempotency-Key`. The middleware also supports future named PUT operations.
`Idempotency` runs after group authentication and before endpoint work. Public Authentication groups run it after realm selection.
All three API CORS configurations permit the header. Reads and preflight skip replay storage.
See [HTTP idempotency](operations/http-idempotency.md) for matching, expiry, errors, encrypted responses, and recovery.

## Session renewal transport

Each API exposes `POST /auth/refresh` under Authentication. The refresh credential replaces bearer authentication for this operation.
A fixed realm policy and refresh authentication run before Idempotency. ExampleApi also requires its API key.
Login and renewal accept `refreshTokenTransport: body|cookie`. Body is the default for native clients.
Cookie mode requires an exact configured Origin and uses a Secure, HttpOnly, SameSite=Strict host cookie.
Responses use `session` with access expiry, idle expiry at issuance, and fixed maximum expiry.
Cookie responses omit the refresh token body attribute. Matching origins receive `Access-Control-Allow-Credentials: true`.
Refresh middleware validates successful responses after replay. It rejects a result that a later rotation replaced.
See [the client guide](../guides/1-identity-and-access.html#session-renewal).
