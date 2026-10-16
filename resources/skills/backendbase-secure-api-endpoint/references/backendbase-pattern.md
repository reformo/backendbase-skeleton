# Backendbase endpoint security pattern

## ExampleApi HTTP flow

```text
API selection -> route match -> API context -> API-key validation
-> controller input validation -> command or query -> application work
```

Slim uses last-in, first-out middleware execution. The API context runs before the key validator. Discover the target framework's execution order.

| Role | Current Backendbase source | Target decision |
| --- | --- | --- |
| API key | `ValidateApiKey` compares the configured key | Header, key storage, public paths, failure contract |
| API context | Middleware supplies API name, header name, and key mode | Typed or validated target request context |
| HTTP policy | Example reads and writes require an API key | Requested target policy and available mechanisms |
| Application access control | Internal `QueueGreeting` uses Shared `AccessControl` | Target privilege contract and caller implementation |
| OpenAPI | Protected Example operations declare `apiKey`; protected identity operations declare bearer authentication | Exact target security schemes |

Backendbase UserApi and SystemAdminApi use the IdentityAndAccess account, token, and session flows. Inspect available mechanisms in other target projects.

Their Authentication, Identity, and Administration modules define each group's realm once through `RealmPolicy`.
Protected groups add `RealmAuthentication`, then `RealmPolicy`. Slim executes `RealmPolicy` first, before token validation.
`RoutePolicy` and `ContactRoutePolicy` supply endpoint settings without changing the group realm.
Public Authentication groups apply only `RealmPolicy`. Realm selection does not require a bearer token.
Adapt middleware names, security policy, and execution order to the target framework.

## Policy matrix

| Policy | Runtime requirement | OpenAPI requirement |
| --- | --- | --- |
| Anonymous | All applicable security layers permit the route | `security: []` |
| API key only | Established key middleware validates the request | One API-key scheme |
| User identity | Target identity mechanism validates the request | The target's identity scheme |
| Named privilege | Required authentication plus an application privilege check | Applicable schemes and documented denial response |

Two schemes in one requirement mean logical AND. Separate requirement objects mean logical OR. Add a scheme only when the target runtime supports it.

## Internal authorization example

The internal greeting handler receives typed access control and asks it for the required privilege before its transaction:

```php
$accessControl = $command->accessControl();
$accessControl->isAllowed(self::REQUIRED_PRIVILEGE);
```

Its caller supplies an `AccessControl` implementation. Tests supply interface doubles. Adapt the port, privilege, and denial behavior to the target. Do not add an HTTP route to reproduce this example.

## Route-level proof

Test applicable cases through the real route stack:

- Allowed access under the selected policy.
- Missing or invalid API key when a key is required.
- Invalid or expired identity credentials when the target supports them.
- A valid token from another realm when the target separates authentication realms.
- A valid identity without the required privilege when the operation requires one.
- Anonymous access when explicitly permitted.

Check status, problem body, and non-dispatch on failure. Compare runtime with OpenAPI, configured CORS, and maintained Bruno requests.

## Current source limits

- The API-key public-path list includes prefixes outside the current ExampleApi route set.
- The shared OpenAPI headers are not all enforced at runtime.
- CORS omits `Accept-Language`.

These limits are audit prompts. Do not copy them as target policy.

## Source provenance

- `src/Backendbase/Shared/Authorization/AccessControl.php`
- `src/Backendbase/Domain/ExampleCatalog/Application/CommandHandlers/QueueGreetingHandler.php`
- `src/Backendbase/Infrastructure/Adapters/Http/Middleware/ValidateApiKey.php`
- `src/Backendbase/Infrastructure/Adapters/Http/Identity/RealmPolicy.php`
- `src/Backendbase/Infrastructure/Adapters/Http/Identity/RealmAuthentication.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/ModuleConfig.php`
- `config/example-api/http-headers.global.php`
- `resources/api-docs/example-api/example-openapi.yml`
- `resources/bruno/example-api/example/`
- `src/Backendbase/Domain/ExampleCatalog/Tests/Application/CommandHandlers/QueueGreetingHandlerTest.php`
- `tests/Infrastructure/Adapters/Http/Middleware/ValidateApiKeyTest.php`
- `tests/Infrastructure/Inbound/ExampleApi/ExampleWriteRoutingTest.php`
- `tests/Infrastructure/Inbound/IdentityApi/ModuleRoutingTest.php`
- `resources/platform/14-security.md`
- `resources/docs/8-authentication-and-authorization.html`
