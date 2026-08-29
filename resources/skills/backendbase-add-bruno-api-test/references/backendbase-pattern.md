# Backendbase Bruno pattern

## Collection roles

| Role | Backendbase reference | Target adaptation |
| --- | --- | --- |
| Root | `opencollection.yml` uses OpenCollection 1.0 | Keep the target's collection format |
| Environment | `environments/local.yml` holds connection, context, auth, and fixture variables | Store safe placeholders only |
| Authentication | Sequence 10 posts credentials and captures a token | Use the target auth contract |
| Lifecycle | Sequences 40-45 create, list, get, change, and delete | Use target dependencies and cleanup |
| Wrapper | `bin/bruno` runs the collection and writes an HTML report | Preserve target reporting controls |

## Lifecycle data flow

```text
safe environment placeholders -> authentication -> capture runtime token
-> create with run-unique data -> capture runtime resource identifier
-> read/list -> update -> verify -> delete -> verify cleanup
```

Use the target runner's response API to capture the documented identifier. It can come from a response body or a response header. Store it in a runtime variable for later requests. Do not put a generated runtime identifier back into a tracked environment file.

A small request shape is:

```yaml
info:
  name: "Get Item"
  type: "http"
  seq: 20
http:
  method: "GET"
  url: "{{baseUrl}}/items/{{itemId}}"
runtime:
  scripts:
    - type: "tests"
      code: |
        expect(res.status).to.equal(200);
        expect(res.getBody()).to.have.property("item");
```

Add all required headers from variables. A write request also needs a safe uniqueness or cleanup plan.

## Semantic request audit

For each request, compare:

- HTTP method and full route path.
- Path and query parameter names, required state, and values.
- Required headers and API selection context.
- API-key and bearer requirements.
- JSON body fields, types, nullability, and defaults.
- Success status, response headers, wrapper keys, fields, and pagination metadata.
- Documented error behavior that the lifecycle intentionally covers.

A passing Bruno request proves only the assertions it contains. It does not prove full OpenAPI conformance. Keep focused PHPUnit boundary and security tests for invalid input and failure branches.

## Repeatable writes and cleanup

- Generate a run-unique external key or identifier before create.
- Capture the server-generated identifier when the contract provides one.
- Make list, detail, update, and delete requests consume those runtime values.
- Delete only data created by the current run.
- Verify the cleanup result or a documented not-found response.
- Report partial cleanup. Do not silently leave shared test data behind.

## Safety and current limitations

These conditions are audit prompts. Do not copy their values or behavior into the target collection.

- The checked `local.yml` keeps token and runtime identifier values empty. Do not add live values.
- The lifecycle generates a run-unique `example-key`; an interrupted run does not block the next create.
- The current create request does not capture or assert the documented insert-ID response header.
- Current list requests do not exercise all runtime pagination inputs.
- Bruno proves selected behavior against a running target. It does not prove complete schema conformance or every failure path.
- The wrapper skips `Authorization`, `Backendbase-Api-Key`, and bodies in reports. Add equivalent protections for target-specific secrets.

## Exact source provenance

- `resources/bruno/example-api/opencollection.yml`
- `resources/bruno/example-api/environments/local.yml`
- `resources/bruno/example-api/auth/authenticate-user.yml`
- `resources/bruno/example-api/example/add-example.yml`
- `resources/bruno/example-api/example/get-example-details.yml`
- `resources/bruno/example-api/example/change-example-details.yml`
- `resources/bruno/example-api/example/delete-example.yml`
- `resources/api-docs/example-api/example-openapi.yml`
- `resources/api-docs/example-api/example/`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example/ModuleConfig.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Example/Handlers/`
- `tests/Infrastructure/UseCase/ExampleApi/`
- `bin/bruno`
- `resources/docs/project.md`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/17-testing.md`
- `resources/platform/24-feature-workflow.md`

The target contract supplies all actual values.
