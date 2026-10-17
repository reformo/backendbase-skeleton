# Backendbase OpenAPI pattern

## Composition

The root document owns API metadata, servers, components, security schemes, tags, and the path reference index.
Each root `paths` entry references one Path Item Object in a logical feature folder.
Each path file owns all methods for one resource path. Metadata and health operations follow the same rule.
Use lowercase `kebab-case` folder and file names. A common components file owns shared request headers and problem responses.

Current Backendbase folders are `authentication/`, `identity/`, `administration/`, `system/`, and `example/` where applicable.
Adapt these group names to the target features.

```yaml
paths:
  /auth/login:
    $ref: './authentication/login.yaml'
  /_status:
    $ref: './system/liveness.yaml'
```

Resolve every reference from its containing file.
In a group file, shared components use `../../common/components.yaml`.
Root schemas use `../{root-spec}.yml#/components/schemas/{schema-name}`. Use the actual target root filename and schema name.

```yaml
get:
  tags:
    - Items
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
```

Declare `Items` with a description in the target root document. The tag name is illustrative; reuse the target endpoint group.

This current Backendbase example requires an API key. Use `security: []` only for an explicitly public operation.

## Tag grouping

In an unmodified Backendbase project, every operation must have a non-empty `tags` list.
Each assigned tag must appear in the root document's declarations.
Use consistent groups across consumer APIs: `Authentication`, `Identity`, `Administration`, `System`, and `Example` where applicable.
Add root descriptions and adapt names to differently named target modules.

New Backendbase controller modules follow the same tag groups using ExampleApi's `ModuleConfig.php` and `Handlers/` layout.
Each handler belongs to its consumer API's `Controllers/{TagName}/Handlers/` directory. Use one handler per operation.
The module registry and route names must match the tag grouping and operation IDs.
Report a mismatched runtime module during contract review. Do not move controllers when only a contract edit is authorized.

Backendbase API validation commands check source and merged schemas and run `bin/check-openapi-tags.php`.
The checker resolves referenced path files and rejects missing, empty, undeclared, or malformed tag names and duplicate declarations.
Other projects must use their own generation, schema validation, and tag validation tools.

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

## Response bodies

In an unmodified Backendbase project, successful bodies use descriptive resource or result keys and omit generic `data` and `statusCode` wrappers.
`ActionPayload` serializes a supplied named body directly. Choose keys in the HTTP adapter.
Current identity uses `user` in UserApi and `admin` in SystemAdminApi. Authentication results use names such as `contactInfo` and `session`.
The HTTP response carries the status code. Problem Details errors retain integer `status` equal to the HTTP response status.
Slim exceptions use the standard HTTP reason phrase as `title`. Preserve mapped domain-specific titles and safe details.
Direct projections, health bodies, and empty responses keep their documented shapes.
Adapt envelope keys, media types, and error formats to the target contract. Do not impose Backendbase envelopes on another project.
Compare source schemas, generated documents, runtime bodies, and maintained request assertions after a response change.

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

Use bearer examples only when the target provides bearer authentication. Backendbase ExampleApi requires an API key and adds USER bearer authentication for protected Example and Identity operations. UserApi and SystemAdminApi use bearer authentication for protected groups and explicitly permit anonymous authentication and health operations.

ACL is an application authorization decision rather than an OpenAPI security scheme. Document its `403` outcome and keep the named privilege explicit in runtime code and tests.

## Shared headers

Backendbase operations reference `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`. Their `required` declarations describe a public contract. Do not assume that a shared `$ref` makes runtime enforce it. Compare each header with bootstrap selection, middleware defaults, and boundary validation. Also compare configured CORS and maintained Bruno requests when applicable.

## Current source limitations

These differences are semantic audit prompts. Do not reproduce them as target behavior.

- Current Example HTTP operations require an API key. All except `getExampleByCriteria` also require USER bearer authentication. Missing or invalid credentials return `401`; unavailable authentication state returns `503`. Internal access-control denial uses the shared forbidden error.
- Example creation requires `lookupValue`. PATCH treats explicit null as omitted, and its schema declares those fields nullable.
- In an unmodified Backendbase project, Example metadata uses `apiMetadata`, lists use plural keys with root pagination, and details use `example`.
- Example timestamps use `DATE_ATOM` and the OpenAPI `date-time` format. List schemas include `createdAt`. Empty detail and readiness maps serialize as JSON objects.
- Backendbase HTTP tests compare executed response shapes with resolved source and merged schemas. These checks cover keys, required fields, types, enums, and media types; they do not validate every format or range.
- All three API CORS configurations allow `Accept-Language`.
- The common file and ExampleApi root use the `apiKey` scheme name and `Backendbase-Api-Key` header. Keep duplicate definitions consistent when changing that contract.
- Current specification validation succeeds despite these semantic differences. Do not treat schema validation as runtime evidence.

## Exact source provenance

- `resources/api-docs/example-api/example-openapi.yml`
- `resources/api-docs/common/components.yaml`
- `resources/api-docs/example-api/example/example-groups.yaml`
- `resources/api-docs/example-api/example/examples.yaml`
- `resources/api-docs/example-api/example/example-details.yaml`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/ModuleConfig.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/ExampleRequestInput.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/Handlers/`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php`
- `config/example-api/http-headers.global.php`
- `resources/bruno/example-api/example/`
- `tests/Infrastructure/Inbound/ExampleApi/`
- `public/example-api/docs/example-api-merged.yml`
- `public/example-api/docs/index.php`
- `composer.json`
- `bin/check-openapi-tags.php`
- `bin/openapi-tags.php`
- `tests/Tools/OpenApiTagsTest.php`
- `bin/deployment/build-release.sh`
- `resources/docs/project.md`
- `resources/docs/6-openapi-and-bruno.html`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/14-security.md`
- `resources/platform/24-feature-workflow.md`

The target contract controls component names and API values. These files provide the composition model only.
