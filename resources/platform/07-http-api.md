# HTTP API Adapter

A Use Case API owns transport behavior. It must not own domain rules, persistence, or transaction control.

## Runtime

- `public/example-api/index.php` selects `ExampleApi` directly.
- `public/index.php` selects an API from `X-Source-Id`.
- API middleware, routes, controllers, configuration, OpenAPI, and Bruno use matching class and slug names.
- `ModuleRoutes` must register each HTTP module explicitly.

## Controller rules

- Business endpoint actions extend `Backendbase\Shared\Http\Actions\Action`.
- A transport-only system handler can be directly invokable when it needs no shared action flow. `Liveness` is the current exception.
- When an action dispatches application behavior, read and validate its PSR-7 input before creating one command or query.
- Map the result to the documented response shape.
- Select the documented status and headers.
- Never access Doctrine, SQL, queues, or aggregate persistence directly.

Group new consumer endpoints with `AuthorizationMiddleware` by default. Leave a route public only for a clear public use case.

Every new OpenAPI operation must reference `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`.

Basis: `resources/docs/5-use-case-api.html`.
