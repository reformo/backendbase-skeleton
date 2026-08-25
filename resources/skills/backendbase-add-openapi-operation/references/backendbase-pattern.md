# Backendbase OpenAPI pattern

## Composition

The root document owns API metadata, servers, root operations, security schemes, tags, and feature references. Feature files own operations for one resource path. A common components file owns shared request headers and problem responses.

```yaml
get:
  operationId: listItems
  parameters:
    - $ref: '../../common/components.yaml#/headers/Accept-Language'
    - $ref: '../../common/components.yaml#/headers/The-Timezone-IANA'
    - $ref: '../../common/components.yaml#/headers/X-Request-Id'
    - $ref: '../../common/components.yaml#/headers/X-Source-Id'
  responses:
    200:
      description: OK
  security:
    - apiKey: []
      bearerAuth: []
```

This security example requires both schemes. Use `security: []` only for an explicitly public operation.

## Draft and reconciliation lifecycle

```text
intended public behavior -> OpenAPI draft -> complete application path
-> route and controller -> OpenAPI reconciliation -> maintained Bruno update
-> source validation -> merged validation -> semantic contract audit
```

An early OpenAPI operation defines the intended public result. It is not final until the implemented route, middleware, validator, command or query, response mapper, and tests agree with it.

## Review mapping

| Contract role | Backendbase source | Target equivalent |
| --- | --- | --- |
| Method and path | Slim module route | Target router declaration |
| Path and query parameters | Request attributes and query parsing | Target boundary input |
| Body required fields | Validator and command construction | Target input contract |
| Security | API middleware and protected route group | Target security policy |
| Success body and headers | Controller response | Target response adapter |
| Error body and status | Problem exception or middleware response | Target public error boundary |

## Semantic contract audit

Compare these values directly. A document validator cannot do this comparison.

| Surface | Check |
| --- | --- |
| Route | Method, full path, placeholder spelling, and route name |
| Input | Location, required state, type, format, nullability, default, range, size, and allowed values |
| Body | `requestBody.required` and schema-level `required` fields |
| Security | Anonymous, API key, bearer, AND or OR composition, privilege denial, and status codes |
| Success | Status, response headers, wrapper keys, fields, pagination metadata, and standard date formats |
| Errors | Runtime status, media type, stable problem code, and documented response reference |
| Headers | Runtime enforcement and OpenAPI parameter; configured CORS and maintained Bruno values when applicable |
| Executable example | Maintained Bruno method, URL, parameters, headers, body, assertions, and lifecycle data |

Use `date-time` for an OpenAPI timestamp unless the target contract defines another supported format.

## Security shapes

```yaml
# Anonymous
security: []

# API key only
security:
  - apiKey: []

# API key AND bearer
security:
  - apiKey: []
    bearerAuth: []

# API key OR bearer
security:
  - apiKey: []
  - bearerAuth: []
```

ACL is an application authorization decision rather than an OpenAPI security scheme in the current pattern. Document its `403` outcome and keep the named privilege explicit in runtime code and tests.

## Shared headers

Backendbase operations reference `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`. Their `required` declarations describe a public contract. Do not assume that a shared `$ref` makes runtime enforce it. Compare each header with bootstrap selection, middleware defaults, and boundary validation. Also compare configured CORS and maintained Bruno requests when applicable.

## Current source limitations

These differences are semantic audit prompts. Do not reproduce them as target behavior.

- API-key and bearer failures return `400`; the contract declares `401` and `403`.
- Details schema includes `typeTargetId`; the controller omits it.
- Runtime list handlers accept `pageSize` and `page`; feature files omit them.
- Some request bodies lack complete `required` declarations.
- Some response fields and timestamp formats do not fully match controller output.
- CORS allowed headers omit `Accept-Language`.
- The common file contains a second API-key scheme name that differs from the root document. Use one authoritative scheme.
- Current specification validation succeeds despite these semantic differences. Do not treat schema validation as runtime evidence.

## Exact source provenance

- `resources/api-docs/example-api/example-openapi.yml`
- `resources/api-docs/common/components.yaml`
- `resources/api-docs/example-api/example/example-groups.yaml`
- `resources/api-docs/example-api/example/examples.yaml`
- `resources/api-docs/example-api/example/example-details.yaml`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example/ModuleConfig.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example/ExampleRequestInput.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example/Handlers/`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/middleware.php`
- `config/example-api/http-headers.global.php`
- `resources/bruno/example-api/example/`
- `tests/Infrastructure/UseCase/ExampleApi/`
- `public/example-api/docs/example-api-merged.yml`
- `public/example-api/docs/index.php`
- `composer.json`
- `bin/deployment/build-release.sh`
- `resources/docs/project.md`
- `resources/docs/6-openapi-and-bruno.html`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/14-security.md`
- `resources/platform/24-feature-workflow.md`

The target contract controls component names and API values. These files provide the composition model only.
