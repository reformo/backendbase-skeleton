# Backendbase Use Case API pattern

## Role-to-target map

| Role | Backendbase reference | Adapt in the target |
| --- | --- | --- |
| API selector | `UseCaseTarget` maps a source ID to class name and slug | Selector key, mapping location, failure response |
| Shared bootstrap | `public/index.php` loads config, container, middleware, and routes | Framework bootstrap and cache behavior |
| API adapter | `Infrastructure/UseCase/ExampleApi` | Namespace, API name, modules, root operations |
| API config | `config/example-api` | Environment keys, security, CORS, cache path |
| Contract | `resources/api-docs/example-api` | Title, servers, schemes, schemas, paths |
| Generated docs | `public/example-api/docs` | Output name and viewer path |
| Executable examples | `resources/bruno/example-api` | Environments, authentication, fixtures |
| Tests | `tests/Infrastructure/UseCase/ExampleApi` | Target test namespace and helpers |

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
5. Root metadata, liveness, readiness, authentication, and fallback handlers only when required.
6. Editable OpenAPI source and a generated merged document.
7. Generation and deployment integration.
8. Focused adapter tests and optional Bruno collection.

## Current source limitations

- `public/example-api/index.php` assigns `$useCaseSlug` and `$useCaseName`, but current `public/index.php` does not read them. The dedicated path still needs a valid `X-Source-Id`. Do not copy those unused assignments as a headerless-selection mechanism.
- API-key and bearer failures return `401`. Named privilege denials return `403`. Invalid time-zone headers return `400`.
- The source selector writes a problem body for an invalid source but does not set the HTTP status explicitly.
- The checked Bruno local environment keeps the access token empty. Use placeholders or runtime token capture instead.

## Exact source provenance

- `public/index.php`
- `public/example-api/index.php`
- `src/Backendbase/Shared/Http/Bootstrap/UseCaseTarget.php`
- `src/Backendbase/Shared/Http/Bootstrap/RequestUriNormalizer.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/routes.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/middleware.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/ModuleRoutes.php`
- `config/example-api/global.php`
- `config/example-api/http-headers.global.php`
- `config/example-api/jwt.global.php`
- `resources/api-docs/example-api/example-openapi.yml`
- `public/example-api/docs/index.php`
- `resources/bruno/example-api/opencollection.yml`
- `composer.json`
- `bin/deployment/build-release.sh`
- `tests/Shared/Http/Bootstrap/UseCaseTargetTest.php`
- `tests/Infrastructure/UseCase/ExampleApi/ModuleRoutingTest.php`
- `resources/docs/5-use-case-api.html`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`

These paths establish decisions. They are not literal templates for another project.
