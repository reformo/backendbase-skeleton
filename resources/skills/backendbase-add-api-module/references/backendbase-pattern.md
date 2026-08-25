# Backendbase API module pattern

## Roles

| Role | Backendbase shape | Target decision |
| --- | --- | --- |
| Module contract | `ModuleRoute` exposes `routeKey()` | Reuse the target route-module contract |
| Module config | `Example/ModuleConfig.php` registers six operations | Choose only requested handlers and policies |
| Registry | `ModuleRoutes` maps route key to invokable class | Register explicitly in the owning API |
| API bootstrap | `routes.php` creates one Slim group per registry item | Preserve target framework grouping |
| Contract | Feature path YAML files own operations | Keep operation IDs and placeholders aligned |
| Test | `ModuleRoutingTest` checks route names and totals | Assert the intended target route set |

## Registration flow

```text
discover owning API -> draft OpenAPI paths -> complete application handlers
-> ModuleConfig -> ModuleRoutes registry -> API routes.php group
-> route middleware -> reconcile OpenAPI and configured CORS
-> update maintained Bruno coverage -> tests
```

In Backendbase, `routes.php` iterates the explicit `ModuleRoutes` map and creates one Slim group from each route key. `ROUTE_KEY` and `routeKey()` must identify that same prefix. A correct handler class is unreachable until the registry contains its module.

A minimal conceptual module is:

```php
final class ModuleConfig implements ModuleRoute
{
    public const string ROUTE_KEY = 'orders';

    public function __invoke(RouteCollectorProxy $routes): void
    {
        $routes->get('', ListOrders::class)->setName('listOrders');
    }

    public function routeKey(): string
    {
        return self::ROUTE_KEY;
    }
}
```

Backendbase groups bearer-authenticated operations with an empty subgroup pattern. This preserves the module prefix while applying bearer middleware. Confirm Slim middleware order with a route test.

## Security decisions

Keep these decisions separate:

| Policy | Runtime shape | OpenAPI shape |
| --- | --- | --- |
| Anonymous | Every applicable security layer intentionally bypasses the route | `security: []` |
| API key only | API-wide key middleware applies; no bearer route group | One API-key scheme |
| Authenticated | API key applies and the route joins the bearer group | API key and bearer in one requirement |
| Privileged | Authenticated route also invokes an ACL or authorization service | Document both authentication schemes and the forbidden response |

Do not call an API-key-only operation public. Bearer identity also does not prove a named privilege. Keep a required ACL decision explicit and test denial through the actual route.

## Semantic module audit

For each operation in the module, compare the route method, complete path, placeholder names, route name, middleware, controller, OpenAPI operation ID, and tests. Include a Bruno request when the target maintains one. Compare shared request headers with configured CORS when the target supports cross-origin clients.

Use both types of route evidence:

- Registry tests prove the module key, route names, and intended route set.
- Full route tests prove middleware order, public access, API-key rejection, bearer rejection, and allowed access as applicable.

## Current source limitations and policy notes

These conditions are audit prompts. Do not reproduce them as target behavior.

- Existing Example route placeholder names are legacy camelCase. Follow the target project's current instructions for new names, then keep runtime and OpenAPI identical.
- Read operations in the Example module are not bearer-protected, but API-key middleware still protects them. Platform guidance protects new consumer endpoints by default.
- Current write routes add bearer middleware but do not perform a named ACL check in their actions.
- Current module tests check registry shape and API-key behavior, but they do not prove every protected route's bearer and ACL behavior.
- Runtime and OpenAPI are not fully aligned on shared headers, security failure status, pagination, and some response fields.
- Do not infer public policy from the HTTP method alone.

## Exact source provenance

- `src/Backendbase/Shared/Http/Actions/ModuleRoute.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/routes.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/ModuleRoutes.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example/ModuleConfig.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/middleware.php`
- `tests/Infrastructure/UseCase/ExampleApi/ModuleRoutingTest.php`
- `resources/api-docs/example-api/example-openapi.yml`
- `resources/api-docs/example-api/example/example-groups.yaml`
- `resources/api-docs/example-api/example/examples.yaml`
- `resources/bruno/example-api/example/`
- `resources/docs/project.md`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/14-security.md`
- `resources/platform/24-feature-workflow.md`

Use these files to understand responsibilities, not to copy Example identifiers.
