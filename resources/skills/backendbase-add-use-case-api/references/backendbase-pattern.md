# Backendbase Use Case API pattern

## Role-to-target map

| Role | Backendbase reference | Adapt in the target |
| --- | --- | --- |
| API selector | `ConsumerApiTarget` maps a source ID to class name and slug | Selector key, mapping location, failure response |
| Shared bootstrap | `public/index.php` loads config, container, middleware, and routes | Framework bootstrap and cache behavior |
| API adapter | `Infrastructure/Inbound/ExampleApi` | Namespace, API name, modules, root operations |
| API config | `config/example-api` | Environment keys, security, CORS, cache path |
| Contract | `resources/api-docs/example-api` | Title, servers, schemes, schemas, paths |
| Generated docs | `public/example-api/docs` | Output name and viewer path |
| Executable examples | `resources/bruno/example-api` | Environments, authentication, fixtures |
| Tests | `tests/Infrastructure/Inbound/ExampleApi` | Target test namespace and helpers |

The effective selection shape is equivalent to:

```php
private const array TARGETS = [
    'client' => ['name' => 'ClientApi', 'slug' => 'client-api'],
];
```

Keep this mapping in one authoritative place. Validate missing and unknown values before building the container.

## Complete surface

A new API normally needs all of these roles:

1. Selector mapping and bootstrap tests.
2. Middleware and route files.
3. Explicit module registry.
4. API-specific settings, CORS, and JWT settings when used.
5. Requested metadata, liveness, readiness, and authentication handlers grouped by OpenAPI tag. Keep transport fallback handlers separate.
6. Editable OpenAPI source and a generated merged document.
7. Generation and deployment integration.
8. Focused adapter tests and optional Bruno collection.

## Controller modules

For an unmodified Backendbase target, use ExampleApi's module structure for new controllers:

```text
Infrastructure/Inbound/{TargetApi}/Controllers/
├── ModuleRoutes.php
└── {OpenApiTag}/
    ├── ModuleConfig.php
    └── Handlers/
        └── {OperationHandler}.php
```

Use the tag's PascalCase name for the module and one handler per operation.
`ModuleConfig` implements `ModuleRoute`. Its `ROUTE_KEY` and `routeKey()` return the same URL prefix.
The owning API's `routes.php` creates groups from the explicit module registry. Route names match OpenAPI operation IDs.
Keep controllers inside their consumer API even when multiple APIs use the same application operations.
Adapt names and equivalent module roles in other projects. Do not copy Example domain names or public routes.

## Current source limitations

- `public/example-api/index.php` assigns `$consumerApiSlug` and `$consumerApiName`, but current `public/index.php` does not read them. The dedicated path still needs a valid `X-Source-Id`. Do not copy those unused assignments as a headerless-selection mechanism.
- Current Example HTTP operations use API-key protection only. Missing or invalid keys return `401`. Internal access-control denial uses the shared forbidden error.
- The source selector writes a problem body for an invalid source but does not set the HTTP status explicitly.
- The checked Bruno local environment contains an API-key placeholder. Do not copy it as a target credential.

## Exact source provenance

- `public/index.php`
- `public/example-api/index.php`
- `src/Backendbase/Infrastructure/Adapters/Http/Bootstrap/ConsumerApiTarget.php`
- `src/Backendbase/Infrastructure/Adapters/Http/Bootstrap/RequestUriNormalizer.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/routes.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/ModuleRoutes.php`
- `config/example-api/global.php`
- `config/example-api/http-headers.global.php`
- `resources/api-docs/example-api/example-openapi.yml`
- `public/example-api/docs/index.php`
- `resources/bruno/example-api/opencollection.yml`
- `composer.json`
- `bin/deployment/build-release.sh`
- `tests/Infrastructure/Adapters/Http/Bootstrap/ConsumerApiTargetTest.php`
- `tests/Infrastructure/Inbound/ExampleApi/ModuleRoutingTest.php`
- `resources/docs/5-use-case-api.html`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`

These paths establish decisions. They are not literal templates for another project.
