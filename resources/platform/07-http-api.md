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
- For PATCH input, preserve omitted nullable fields and reject supplied values with invalid types before command construction.
- Dispatch one command or query for one endpoint operation. For writes, put the public resource identity in the command and keep authoritative lookup in its handler.
- Pass typed `AccessControl` into a privileged command. Keep the named privilege decision in its application handler.
- Map the result to the documented response shape.
- Select the documented status and headers.
- Never access Doctrine, SQL, queues, or aggregate persistence directly.

Group new consumer endpoints with `AuthorizationMiddleware` by default. It validates through `TokenValidator` and supplies typed `AccessControl`. Leave a route public only for a clear public use case.

Every new OpenAPI operation must reference `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`.

Basis: `resources/docs/5-use-case-api.html`.
