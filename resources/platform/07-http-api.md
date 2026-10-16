# HTTP API Adapter

A Use Case API owns transport behavior. It must not own domain rules, persistence, or transaction control.

## Runtime

- All public entry points use `public/index.php`, which selects an API from `X-Source-Id` through `ConsumerApiTarget`.
- Invalid source selection returns HTTP `400` with `application/problem+json` and a matching Problem Details status before container creation.
- `public/example-api/index.php` assigns API name and slug variables that the shared bootstrap does not read. Its requests still require `X-Source-Id: example`.
- API middleware, routes, controllers, configuration, OpenAPI, and Bruno use matching class and slug names.
- `ModuleRoutes` must register each HTTP module explicitly.
- UserApi and SystemAdminApi each own their controllers and route registration. Their route policies select a fixed authentication realm.
- Group new endpoint controllers by OpenAPI tag inside their owning API. Use `Controllers/{TagName}/ModuleConfig.php` and `Controllers/{TagName}/Handlers/`.
- UserApi and SystemAdminApi modules are Authentication, Identity, System, and SystemAdminApi Administration.
- Follow ExampleApi's module pattern: `ModuleConfig` implements `ModuleRoute`, declares `ROUTE_KEY`, and registers one handler per operation under `Handlers`.
- `ModuleRoutes` maps prefixes to module classes. `routes.php` creates their route groups. Route names match OpenAPI operation IDs.
- Use fluent route declarations in `ModuleConfig`, following ExampleApi. Chain the HTTP method, `setName()`, and endpoint-specific `add()` calls.
- Place routes with common middleware in `group()`. Attach shared middleware once to that group.
- Reusable identity input, dispatch, and policy helpers live under `Infrastructure/Adapters/Http/Identity`. Administration input belongs inside SystemAdminApi's Administration module.

Authentication, Identity, and Administration modules define their realm once per group through `RealmPolicy`.
Identity and Administration modules use an empty-prefix subgroup. The subgroup adds `RealmAuthentication`, then its fixed `RealmPolicy`.
Slim runs the last added middleware first. The group policy supplies `expectedRealm` before authentication runs.
`RoutePolicy` supplies only the endpoint action. `ContactRoutePolicy` supplies only contact settings. Neither policy sets or replaces the group realm.
Endpoint action and contact policies run after group authentication. Public Authentication groups apply only `RealmPolicy` and do not require a bearer token.
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

Protect new consumer endpoints with the existing API-key middleware by default. Declare anonymous access explicitly. Example reads and writes use API-key protection only.

Example POST and PATCH read bounded raw JSON through `ExampleRequestBody`. The root and supplied non-null details must be objects.
The API preserves accepted text and detail values. It does not escape HTML, transform credential-like detail names, or derive image URLs.
Example types are `system` and `user`. Groups, keys, values, and target identifiers follow the context value constraints.

`X-Source-Id` selects `example`, `user`, or `system-admin`. The header does not grant access.
Realm endpoints use route-owned bearer authentication. Public authentication and health routes explicitly permit anonymous access.
Optional locale, timezone, and trace headers use documented defaults.
All three API CORS configurations permit `Accept-Language`, timezone, trace, and source headers.

Basis: `resources/docs/5-use-case-api.html`.

The bootstrap validates supplied trace UUIDs and timezone values before logger creation. Missing optional headers retain documented defaults.
JSON body limits run before parsing. Identity inputs require JSON objects, while token, session, and Account deletion require empty bodies.

Browser OPTIONS preflight can select a fixed consumer from its API path without an `X-Source-Id` value.
Ordinary requests still require the declared source header. Preflight does not invoke authentication or business handlers.
