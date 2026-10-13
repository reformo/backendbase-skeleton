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

Current Backendbase operations use API-wide key middleware. If the target requires identity middleware, use its established grouping and verify middleware order.

## Security decisions

Keep these decisions separate:

| Policy | Runtime shape | OpenAPI shape |
| --- | --- | --- |
| Anonymous | Every applicable security layer intentionally bypasses the route | `security: []` |
| API key only | API-wide key middleware applies; no bearer route group | One API-key scheme |
| Authenticated, when required and available | Use the target identity middleware and key policy | Declare the schemes used by the target |
| Privileged | Authenticated route also invokes an ACL or authorization service | Document both authentication schemes and the forbidden response |

Do not call an API-key-only operation public. Bearer identity also does not prove a named privilege. Keep a required ACL decision explicit and test denial through the actual route.

## Semantic module audit

For each operation in the module, compare the route method, complete path, placeholder names, route name, middleware, controller, OpenAPI operation ID, and tests. Include a Bruno request when the target maintains one. Compare shared request headers with configured CORS when the target supports cross-origin clients.

Use both types of route evidence:

- Registry tests prove the module key, route names, and intended route set.
- Full route tests prove middleware order, public access, API-key rejection, bearer rejection, and allowed access as applicable.

## Current source limitations and policy notes

These conditions are audit prompts. Do not reproduce them as target behavior.

- Example route placeholders use `kebab-case`, matching OpenAPI and Bruno. Follow the target naming rule for new paths.
- All Example reads and writes require an API key. New consumer endpoints use this protection by default.
- Example write commands and handlers have no identity or privilege inputs. Internal greeting authorization remains in its handler.
- Current route tests prove API-key rejection and successful writes without identity attributes. Adapt equivalent tests to the target policy.
- Shared-header enforcement and CORS remain incomplete. Group pagination and security status codes now match their documented behavior. Detail timestamps still use the nonstandard OpenAPI format `datetime`.
- Do not infer public policy from the HTTP method alone.

## Exact source provenance

- `src/Backendbase/Infrastructure/Adapters/Http/Actions/ModuleRoute.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/routes.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/ModuleRoutes.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/ModuleConfig.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php`
- `tests/Infrastructure/Inbound/ExampleApi/ModuleRoutingTest.php`
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
