# HTTP API Adapter

A Use Case API owns transport behavior. It must not own domain rules, persistence, or transaction control.

## Runtime

- Both public entry points use `public/index.php`, which selects an API from `X-Source-Id` through `ConsumerApiTarget`.
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
- Select the documented status and headers.
- Never access Doctrine, SQL, queues, or aggregate persistence directly.

Group new consumer endpoints with `AuthorizationMiddleware` by default. It validates through `TokenValidator` and supplies typed `AccessControl`. Leave a route public only for a clear public use case.

Every new OpenAPI operation must reference `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`.

Basis: `resources/docs/5-use-case-api.html`.
