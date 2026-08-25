# HTTP API Adapter

A Use Case API owns transport behavior. It must not own domain rules, persistence, or transaction control.

## Runtime

- `public/example-api/index.php` selects `ExampleApi` directly.
- `public/index.php` selects an API from `X-Source-Id`.
- API middleware, routes, controllers, configuration, OpenAPI, and Bruno use matching class and slug names.
- `ModuleRoutes` must register each HTTP module explicitly.

## Controller rules

- Extend `Backendbase\Shared\Http\Actions\Action`.
- Read PSR-7 path, query, header, and parsed-body data.
- Sanitize and validate all untrusted input before bus dispatch.
- Create one command or query.
- Map the result to the documented response shape.
- Select the documented status and headers.
- Never access Doctrine, SQL, queues, or aggregate persistence directly.

Group new consumer endpoints with `AuthorizationMiddleware` by default. Leave a route public only for a clear public use case.

Every new OpenAPI operation must reference `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`.

Basis: `resources/docs/5-use-case-api.html`.
