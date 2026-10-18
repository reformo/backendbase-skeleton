# Backendbase API module pattern

## Roles

| Role | Backendbase shape | Target decision |
| --- | --- | --- |
| Module contract | `ModuleRoute` exposes `routeKey()` | Reuse the target route-module contract |
| Module config | `Example/ModuleConfig.php` registers operations tagged `Example` | Match the target OpenAPI tag and choose requested handlers and policies |
| Handlers | `Example/Handlers/` contains one action per operation | Keep each action inside its tag's module and owning API |
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

For new controllers in an unmodified Backendbase target, use `Controllers/{TagName}/ModuleConfig.php` and `Controllers/{TagName}/Handlers/`.
Use the OpenAPI tag's PascalCase module name. Keep one handler per operation, following ExampleApi's layout.
Other projects use their established namespace and equivalent module roles.

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

Current Backendbase Example operations use API-wide key middleware. All except `getExampleByCriteria` also require USER bearer authentication. ExampleApi and UserApi share USER identity services; SystemAdminApi uses ADMIN.
Use fluent route declarations, following ExampleApi. Keep endpoint-specific policy on each route and attach common middleware once through `group()`.
Authentication, Identity, and Administration modules define the realm once per group through `RealmPolicy`.
`RoutePolicy` supplies only the endpoint action. `ContactRoutePolicy` supplies only contact settings. Neither policy sets or replaces the group realm.
Identity and Administration modules use an empty-prefix subgroup with `Idempotency`, `RealmAuthentication`, followed by a fixed `RealmPolicy` in the declaration.
Slim executes the last added middleware first. The realm policy therefore runs before authentication; endpoint policies run after group authentication.
Public Authentication groups apply `RealmPolicy` and write replay through `Idempotency` and do not require a bearer token.
Use non-static group callbacks because Slim binds them to the dependency container. Keep anonymous operations outside protected groups.
Adapt the middleware names, policy, and callback behavior to the target project.

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

- Registry tests prove the module key, tag-to-handler placement, route names, and intended route set within the selected API.
- Full route tests prove middleware order, public access, API-key rejection, bearer rejection, and allowed access as applicable.

## Current source limitations and policy notes

These conditions are audit prompts. Do not reproduce them as target behavior.

- Example route placeholders use `kebab-case`, matching OpenAPI and Bruno. Follow the target naming rule for new paths.
- All Example reads and writes require an API key. All except `getExampleByCriteria` also require a USER bearer token. Discover each new operation's complete security policy.
- Example write commands and handlers have no identity or privilege inputs. Internal greeting authorization remains in its handler.
- Current route tests prove API-key rejection and successful writes without identity attributes. Adapt equivalent tests to the target policy.
- Shared-header presence requirements remain stricter in OpenAPI than at runtime. All three API CORS configurations allow `Accept-Language`. Group pagination and security status codes match their documented behavior. Detail timestamps use the OpenAPI `date-time` format.
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
