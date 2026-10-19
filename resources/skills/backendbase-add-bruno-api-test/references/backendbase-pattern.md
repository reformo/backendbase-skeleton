# Backendbase Bruno pattern

## Collection roles

| Role | Backendbase reference | Target adaptation |
| --- | --- | --- |
| Root | `opencollection.yml` uses OpenCollection 1.0 | Keep the target's collection format |
| Environment | `environments/local.yml` holds connection, context, and fixture variables | Store safe placeholders only |
| Security | Current requests send an API key without a token flow | Discover the target security mechanism |
| Lifecycle | Sequences 40-45 create, list, get, change, and delete | Use target dependencies and cleanup |
| Wrapper | `bin/bruno` runs the collection and writes an HTML report | Preserve target reporting controls |

## Lifecycle data flow

```text
safe environment placeholders -> credentials required by the target policy
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
        test("Response matches the API contract", function () {
          expect(res.status).to.equal(200);
          expect(res.getBody()).to.have.property("item");
        });
```

Add all required headers from variables. Add token acquisition only when the target provides that flow. A write request also needs a safe uniqueness or cleanup plan.

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
Require named test results in the retained report. A request result alone does not establish that the report contains contract-test evidence.

## Repeatable writes and cleanup

- Generate a run-unique external key or identifier before create.
- Capture the server-generated identifier when the contract provides one.
- Make list, detail, update, and delete requests consume those runtime values.
- Delete only data created by the current run.
- Verify the cleanup result or a documented not-found response.
- Report partial cleanup. Do not silently leave shared test data behind.

## HTTP idempotency

For an unmodified Backendbase target, follow [HTTP idempotency](../../../platform/operations/http-idempotency.md).
Other targets use their discovered header, key scope, retention, and failure contract.

The Backendbase write examples use `{{$randomUUID}}` for a new intended operation.
A replay test must instead retain one generated key in a runtime variable and reuse it for both requests.
Keep the method, path, query string, exact body bytes, credentials, and fingerprinted representation headers unchanged.
Do not regenerate run-unique body fields between attempts. A new intended operation or corrected payload needs a new key.

When replay testing is requested, select the applicable cases on an authorized, prepared target:

- A completed matching request returns the stored status, body, and selected headers within the replay window.
- Verify that the retry does not create another resource or repeat a delivery when that effect is observable.
- A missing or malformed key returns 400 before the operation starts.
- Changed request content with the same scoped key returns 422.
- An active or unresolved claim returns 409. Use prepared fixtures for this case; do not delete claims to force execution.
- Expired or revoked credentials return 401 before protected replay, including retries after logout or token revocation.

Backendbase retains completed responses for 24 hours. Do not automatically retry an uncertain operation after this window.
Keep the original key after a timeout or storage failure. Inspect the outcome before starting another operation.

## Safety and current limitations

These conditions are audit prompts. Do not copy their values or behavior into the target collection.

- The checked `local.yml` contains an API-key placeholder and an empty runtime identifier. Do not add live values.
- The lifecycle generates a run-unique `example-key`; an interrupted run does not block the next create.
- The current create request does not capture or assert the documented insert-ID response header.
- Bruno proves selected behavior against a running target. It does not prove complete schema conformance or every failure path.
- The wrapper skips `Authorization`, `Backendbase-Api-Key`, and bodies in reports. Add equivalent protections for target-specific secrets.

## Exact source provenance

- `resources/bruno/example-api/opencollection.yml`
- `resources/bruno/example-api/environments/local.yml`
- `resources/bruno/example-api/example/add-example.yml`
- `resources/bruno/example-api/example/get-example-details.yml`
- `resources/bruno/example-api/example/change-example-details.yml`
- `resources/bruno/example-api/example/delete-example.yml`
- `resources/api-docs/example-api/example-openapi.yml`
- `resources/api-docs/example-api/example/`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/ModuleConfig.php`
- `src/Backendbase/Infrastructure/Inbound/ExampleApi/Controllers/Example/Handlers/`
- `tests/Infrastructure/Inbound/ExampleApi/`
- `bin/bruno`
- `resources/docs/project.md`
- `resources/platform/07-http-api.md`
- `resources/platform/08-api-contracts.md`
- `resources/platform/17-testing.md`
- `resources/platform/24-feature-workflow.md`

The target contract supplies all actual values.
