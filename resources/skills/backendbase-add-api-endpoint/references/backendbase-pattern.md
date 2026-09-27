# Backendbase endpoint pattern

## End-to-end endpoint flow

```text
discover affected API -> draft OpenAPI contract -> complete application path
-> PSR-7 request -> optional sanitizer -> boundary validator
-> command/query bus or application orchestrator
-> confirmed identifier or declared read result -> response mapper -> PSR-7 response
-> reconcile OpenAPI -> update maintained Bruno lifecycle -> semantic contract audit
```

| Endpoint concern | Backendbase reference | Target adaptation |
| --- | --- | --- |
| Base action | HTTP adapter `Action` catches known problem exceptions | Use the target error boundary |
| Input validation | `ExampleRequestInput` validates enum, scalar, object, boolean, and positive-integer fields | Encode the requested external contract |
| Create | `NewExample` sends a command and returns `204` plus insert ID | Select target create status and headers |
| Read | `Examples` sends a query and maps a page | Use the target read model and schema |
| Update/delete lookup | Change and remove actions send `EntryIdentity` in one command | Resolve the aggregate and missing-state decision in the handler |
| Routing | `ModuleConfig` names and protects routes | Match target operation ID and policy |
| Tests | Input, read, write, security, and conditional not-found tests | Use focused target doubles |

## Boundary example

```php
$payload = PayloadSanitizer::sanitize($request->getParsedBody());
$quantity = RequestInput::positiveInteger($payload['quantity'] ?? null, 'quantity');
$commandBus->handle(new CreateItem($itemId, $quantity));
```

Do not use this shape to skip required-field checks. Validate the complete request before constructing a command.

`PayloadSanitizer` escapes or transforms values. It does not prove presence, type, format, range, size, or membership in an allowed set. Use a request-specific validator or explicit typed construction for those rules.

## Complete use-case requirement

An endpoint can dispatch only a runtime-resolvable application path:

```text
route -> action -> command/query -> registered handler -> port -> registered adapter
```

If the command or query does not exist, create the complete use case first. Include the handler, required ports or adapters, container registration, focused tests, and one real bus-resolution test. Do not create a message class only to make the controller compile.

Endpoint work does not authorize a database change. Add a migration only when the user explicitly requests the exact schema change.

## Contract draft and reconciliation

Use OpenAPI twice:

1. Draft the intended method, path, inputs, security, responses, and errors before implementation.
2. Reconcile every field with the final route, middleware, validator, command or query, response mapper, and tests.

For each field, compare its name, location, required state, type, nullability, default, format, and limits. A schema validator proves document structure. It does not prove runtime alignment.

The Backendbase shared headers are `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`. If the target declares a header required, either enforce it at runtime or correct the contract. Also permit it in CORS for cross-origin clients.

## Application and error boundaries

Invoke one application operation per endpoint through a command, query, or write-result orchestrator. Do not use a controller query to translate public identity for a write. Carry the identity in the command. Resolve the aggregate and enforce current invariants through a write port in the command handler.

The Infrastructure HTTP error handler maps known domain errors, Slim failures, and unexpected failures. Do not build ad hoc error arrays in a controller or expose internal exception details.

## Client-visible write results

For an identifier-only response, generate the identifier before a create command or reuse an existing resource's public identity. Return it after successful execution. Current Backendbase examples are `RegisterAccount` (`201`, `accountUuid`) and `NewExample` (`204`, `Backendbase-Insert-Id`). Adapt names, statuses, and headers to the target contract.

For a resource representation, the permitted application-orchestrator path is:

```text
HTTP action -> typed application input -> application orchestrator
    -> command bus -> handler -> committed write
    -> query bus or authorized read port -> declared result
HTTP action -> documented response
```

This is an allowed design, not the current create-endpoint implementation. Verify the orchestrator and all dependencies through the target composition root. For immediate representations, prove that the selected read source observes the committed write. Test missing results and read failures after commit without repeating the write.

## Security layers

Decide each layer independently:

- API-key middleware controls access to the API surface.
- Bearer middleware validates identity and supplies identity attributes.
- The application handler checks the requested privilege before protected work.
- An OpenAPI operation is anonymous only when its runtime policy permits anonymous access and it declares `security: []`.

An API-key-only route is not public. A bearer-protected route is not fully authorized when the use case also requires a named privilege.

## Security and result tests

For a protected operation, test missing credentials, invalid credentials, insufficient permission, and successful authorized access as applicable. For an explicitly public operation, test successful access without credentials.

Compare the route middleware with the OpenAPI operation security declaration. Test their agreement at the runtime boundary.

Use result-specific behavior:

- A missing single resource can return the documented problem response.
- An empty collection normally returns the documented successful empty shape.
- A paginated collection needs tests for defaults, maximum size, invalid values, empty pages, and out-of-range pages.
- Boundary-validation and controller-local mapping failures must not dispatch a write command. Domain preconditions remain authoritative in the command handler.

Use a focused test matrix:

| Test | Evidence |
| --- | --- |
| Valid read or write | Exact typed message reaches the correct bus |
| Write-result orchestrator | Typed input reaches the orchestrator; the authorized read follows the committed write |
| Invalid input | Stable problem response and no bus dispatch |
| Missing single resource | Documented not-found response |
| Empty collection | Successful empty response shape |
| Response mapping | Exact status, headers, fields, and date formats |
| Route registration | Method, path, route name, and module registry agree |
| Route security | The real middleware stack rejects and allows the expected requests |
| Contract | Runtime and OpenAPI agree; maintained Bruno and configured CORS agree when applicable |

## Current source limitations

These conditions are audit prompts. Do not reproduce them as target behavior.

- `NewExample` validates `lookupValue` and optional body field types through `ExampleRequestInput`. It still casts route group data, and the sanitizer is not a complete validator.
- `PayloadSanitizer` is not a complete validator and includes special transformations for some field names.
- API-key and bearer failures return `401`. Named privilege denials return `403`. Invalid time-zone headers return `400`.
- The four shared headers are declared required, but runtime enforcement and CORS are not fully aligned.
- Current privileged commands and the account-list query carry typed access control. Their handlers enforce named privileges before protected work.
- `tests/ExampleApiTestCase.php` loads obsolete HTTP adapter paths. Use current focused tests or repair a full-stack helper only when the requested test needs it.

## Exact source provenance

- `src/Backendbase/Infrastructure/Adapters/Http/Actions/Action.php`
- `src/Backendbase/Infrastructure/Adapters/Http/DomainErrorProblemDetailsMapper.php`
- `src/Backendbase/Infrastructure/Adapters/Http/HttpErrorHandler.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/middleware.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/ModuleConfig.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/ExampleRequestInput.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/Handlers/NewExample.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Account/Handlers/RegisterAccount.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/Handlers/Examples.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/Handlers/ExampleDetails.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/Handlers/ChangeExampleDetails.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/Handlers/RemoveExample.php`
- `src/Backendbase/Domain/ExampleCatalog/Domain/EntryIdentity.php`
- `tests/Infrastructure/Inbound/ExampleApi/ExampleInputValidationTest.php`
- `tests/Infrastructure/Inbound/ExampleApi/ExampleReadControllersTest.php`
- `tests/Infrastructure/Inbound/ExampleApi/ExampleWriteControllersTest.php`
- `tests/Infrastructure/Inbound/ExampleApi/ExampleNotFoundTest.php`
- `tests/Infrastructure/Inbound/ExampleApi/ModuleRoutingTest.php`
- `tests/ExampleApiTestCase.php`
- `resources/api-docs/example-api/example-openapi.yml`
- `resources/api-docs/example-api/example/`
- `resources/bruno/example-api/example/`
- `resources/docs/project.md`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/14-security.md`
- `resources/platform/16-errors-observability.md`
- `resources/platform/24-feature-workflow.md`

These sources show adapter boundaries. The target project's public contract controls names and values.
