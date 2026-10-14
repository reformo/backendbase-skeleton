# HTTP API Adapter

A Use Case API owns transport behavior. It must not own domain rules, persistence, or transaction control.

## Runtime

- All public entry points use `public/index.php`, which selects an API from `X-Source-Id` through `ConsumerApiTarget`.
- `public/example-api/index.php` assigns API name and slug variables that the shared bootstrap does not read. Its requests still require `X-Source-Id: example`.
- API middleware, routes, controllers, configuration, OpenAPI, and Bruno use matching class and slug names.
- `ModuleRoutes` must register each HTTP module explicitly.

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
- Problem Details errors retain their integer `status` field. It must equal the HTTP response status.
- Slim exception titles use the standard HTTP reason phrase. Domain error titles describe the mapped problem type.
- Never access Doctrine, SQL, queues, or aggregate persistence directly.

Protect new consumer endpoints with the existing API-key middleware by default. Declare anonymous access explicitly. Example reads and writes use API-key protection only.

`X-Source-Id` selects `example`, `user`, or `system-admin`. The header does not grant access.
Realm endpoints use route-owned bearer authentication. Public authentication and health routes explicitly permit anonymous access.
Optional locale, timezone, and trace headers use documented defaults.

Basis: `resources/docs/5-use-case-api.html`.

The bootstrap validates supplied trace UUIDs and timezone values before logger creation. Missing optional headers retain documented defaults.
JSON body limits run before parsing. Identity inputs require JSON objects, while token, session, and Account deletion require empty bodies.

Browser OPTIONS preflight can select a fixed consumer from its API path without an `X-Source-Id` value.
Ordinary requests still require the declared source header. Preflight does not invoke authentication or business handlers.
