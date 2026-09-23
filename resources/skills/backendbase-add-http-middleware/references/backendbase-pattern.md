# Backendbase HTTP middleware pattern

## Stack roles

The Example API registers IP resolution, API-key validation, and an inline API-context middleware. Because Slim wraps middleware as a stack, the inline context middleware is registered last so it runs before `ValidateApiKey` and supplies:

- `useIdentifierAsApiKey`
- `apiName`
- `apiKeyHeaderName`

The current registration order is IP resolver, API-key validator, then API context. The effective execution order starts with API context, then API-key validation, then the earlier middleware. Treat this as Slim-specific last-in, first-out behavior. Discover the target framework's rule before copying the order.

A generic PSR-15 shape is:

```php
final readonly class RequestContextMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute('requestContext', $this->context);

        return $handler->handle($request);
    }
}
```

## Review map

| Middleware role | Backendbase evidence | Target equivalent |
| --- | --- | --- |
| Order | Full application route test | Target framework stack test |
| Required attributes | Downstream middleware unit test | Target request context contract |
| Bypass | Explicit public-path test | Target route policy |
| Failure | Status, content type, and stable code assertion | Target public error contract |
| CORS | API-specific allowed-header config | Target cross-origin configuration |
| Contract | OpenAPI header and response declarations | Target API specification |

## Attribute dependency audit

Build a small dependency table before changing the stack:

| Consumer | Required value | Producer | Required execution relation |
| --- | --- | --- | --- |
| API-key validator | API name, header name, and key mode | API-context middleware | Context executes first |
| Bearer middleware | Authorization and timezone headers | Client plus boundary policy | Validate before typed identity attributes |
| Controller | Authorized identity and ACL | Bearer middleware | Middleware executes before action |

Add one full-route test for each important relation. A direct middleware unit test cannot prove registration order.

## Header contract audit

For each documented request header, compare the runtime and OpenAPI surfaces. Include CORS and Bruno only when the target configures or maintains them:

1. Runtime middleware or bootstrap validation and defaults.
2. OpenAPI parameter name, required state, schema, and examples.
3. Configured CORS `Access-Control-Allow-Headers` behavior.
4. Maintained Bruno request variables and values.

If one surface differs, either correct it or report the intentional difference. Adding an OpenAPI `$ref` does not make runtime enforce the header.

## Public bypass and security

Keep bypasses limited to routes owned by the target API. A public operation needs both a matching runtime bypass and `security: []`. An API-key-only operation is protected. Route-level bearer middleware and a named ACL decision remain separate layers.

## Current source limitations

These conditions are audit prompts. Do not reproduce them as target behavior.

- `ValidateApiKey` is active in current source.
- The public-path list includes paths that are not part of the Example API route set.
- OPTIONS returns an empty `200`; the fallback action returns `204` for OPTIONS. Keep one intentional contract.
- The four shared OpenAPI headers are not all enforced at runtime, and CORS omits `Accept-Language`.
- Existing write routes add bearer middleware and enforce named privileges in their application handlers.
- OpenAPI validation can succeed while middleware status, headers, or bypass behavior differs. Run a semantic audit.

## Exact source provenance

- `src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php`
- `src/Backendbase/Infrastructure/Adapters/Http/Middleware/ValidateApiKey.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Middleware/AuthorizationMiddleware.php`
- `config/example-api/http-headers.global.php`
- `resources/api-docs/example-api/example-openapi.yml`
- `resources/api-docs/common/components.yaml`
- `resources/bruno/example-api/`
- `tests/Infrastructure/Adapters/Http/Middleware/ValidateApiKeyTest.php`
- `tests/Infrastructure/Inbound/ExampleApi/Middleware/AuthorizationMiddlewareTest.php`
- `tests/Infrastructure/Inbound/ExampleApi/ModuleRoutingTest.php`
- `resources/docs/project.md`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/14-security.md`
- `resources/platform/16-errors-observability.md`

Use the target framework's stack semantics when they differ from Slim.
