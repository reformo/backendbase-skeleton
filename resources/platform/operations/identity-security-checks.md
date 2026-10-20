# Repeatable identity security checks

Use dedicated disposable MySQL and Redis services.
The HTTP suite creates and removes one uniquely named MySQL database.
It leaves short-lived Redis verification counters and session records in the disposable Redis service.
Do not run this suite against shared application services.
Start with a fresh disposable Redis service for each complete verification sequence.
Repeated runs can reach the normal twenty-send hourly limit for the loopback caller.
Recreate only the owned disposable service before repeating the sequence. Read its assigned port again.

Set `BACKENDBASE_SERVICE_TESTS=1` only after identifying the prepared targets.
Set `BACKENDBASE_TEST_MYSQL_PORT` and `BACKENDBASE_TEST_MYSQL_PASSWORD` for the loopback MySQL root connection.
Set `BACKENDBASE_TEST_REDIS_PORT` and `BACKENDBASE_TEST_REDIS_PASSWORD` for the loopback Redis connection.

Run:

```sh
python3 tests/Security/identity_http.py --scan
```

The suite uses a temporary application copy and the production HTTP entry point, configuration, routes, middleware, Doctrine adapters, and Redis adapters.
It replaces only the contact notifier with a private local file sink.
It creates separate fixture Accounts and credentials without using the personal initial Admin seeder.
The operator fixture has accepted User registration, verified ownership, and an approved Admin actor.
The fixture database, temporary server, and scan containers are removed when the run ends.
The temporary application has a dedicated replay encryption key. Each intended write supplies a new `Idempotency-Key`.

## Coverage

The deterministic HTTP sequence checks all 55 operations across ExampleApi, UserApi, and SystemAdminApi.
It verifies operator login and protected identity access in both realms, including runs without `--scan`.
It verifies successful registration and authentication, realm isolation, approval, removal, restoration, token expiry, revocation, logout, and Account closure.
It also checks missing credentials, invalid and oversized input, contact confirmation, and resend cooldown rejection.
The default User registration policy requires mobile proof only. Test all four configured proof combinations.
Stored-email verification runs after User login. Preserve legacy sessions and separate User acceptance from Admin ownership checks.
ExampleApi uses a separate disposable User Account for registration, contact verification, identity changes, and the six Example operations.
New OpenAPI operations without a recorded HTTP result fail the suite.
Every write operation must also record missing-key rejection and unchanged-request replay behavior.

Bounded write probes check missing and malformed replay keys, authentication, malformed JSON, oversized bodies, and changed-request rejection.
Protected replay probes remove credentials before retrying. Revocation, logout, and closure retries must return 401.
Cross-account probes reject contact confirmation and resend attempts in all three APIs.
Public login probes reject replay with another Account identifier.
These checks use separate request keys. Only replay probes intentionally reuse a key.

Three authenticated ZAP scans target `GET /identity` and `GET /_status` in each API.
Fresh sessions must access the protected route before and after each scan.
Mutation routes use the deterministic sequence because scanning them can revoke or close the scan identity.
Readiness uses a deterministic check that requires MySQL and Redis readiness while unprovisioned dependencies remain unavailable.
This scope does not claim exhaustive payload fuzzing of every mutation.

The suite writes operation statuses and probe categories to `artifacts/identity-http.json`.
It writes sanitized ZAP reports and scan scope to `artifacts/zap-identity`.
The security workflow retains these artifacts for 30 days.
ZAP authentication tokens are removed from retained reports.

## Bruno contract checks

Use the prepared disposable MySQL and Redis targets for complete consumer verification:

```sh
python3 tests/Security/identity_http.py --bruno
```

Install Bruno CLI 4.0.0, matching the security workflow. The runner rejects another version.
The runner executes the maintained requests and their assertions across all three collections.
It supplies private temporary environment values and uses a separate Account for each API lifecycle.
It retrieves contact codes from the private notifier sink and waits for the normal resend cooldown.
Resend runs before confirmation. Registration runs after the required mobile proof.
Login precedes protected requests. Fresh login separates token revocation, logout, and Account closure.
The Admin invitation captures its actor identifier for approval, removal, and history checks.
Account closure runs after the Example and administration operations.

The coverage gate requires every maintained HTTP request file to have a passing result and a recorded passing contract test.
Each response assertion script uses a named Bruno test so the report records its result.
It retains per-phase HTML, JSON, and sanitized logs under `artifacts/bruno-verification`.
Reports omit all headers and request and response bodies. Runtime credentials remain in the temporary application directory.
The runner removes its database and temporary application when execution ends. Remove the disposable services after all checks finish.
The security workflow runs this sequence and retains its sanitized reports with the other HTTP evidence.

## ExampleApi checks

The workflow starts ExampleApi on loopback port `8081`.
Keep port `8080` free because ZAP add-on updates can temporarily bind that default proxy port.
The workflow uses the same API address for readiness, authentication, Bruno, write fixtures, and the ZAP target override.
The local Bruno environment keeps its default address. CI overrides `baseUrl` explicitly.

Run the scanner startup regression in an isolated container:

```sh
docker run --rm --entrypoint python3 --tmpfs /zap/wrk:mode=1777 \
  --mount "type=bind,src=$PWD/tests/Security/zap_startup.py,dst=/zap/wrk/zap_startup.py,readonly" \
  ghcr.io/zaproxy/zaproxy@sha256:781a2bdaea47324e7bab583e2263f21d257b0aee61ed51521a5be45f5f5081ef \
  /zap/wrk/zap_startup.py 8081
```

The fixture retains add-on updates and alpha rules. It verifies API requests and report creation after scanner startup.
It accepts passive warnings from its dummy HTTP server. The application security workflow retains its existing failure policy.
Use `8080` as the final argument to reproduce the former bind conflict. That diagnostic run prints the scanner result.

The security workflow configures temporary USER and ADMIN signing keys for its disposable services.
It seeds a fixture Account and obtains a USER session through ExampleApi login.
The Bruno `example` folder and Example write fixtures send the API key, bearer token, and required `Idempotency-Key`.
Boundary checks reject writes without a bearer token.
The Example ZAP contract excludes Authentication, Identity, and readiness operations.
It retains the six Example operations, metadata, and liveness. The scan supplies the API key and USER bearer token.
Two ordered ZAP replacer rules add the replay header and replace its placeholder with a UUID for each request.
This prevents earlier scan responses from masking changed probe payloads. See [ZAP token processing](https://www.zaproxy.org/docs/desktop/addons/replacer/#token-processing).
The workflow redacts both credentials before uploading reports from `artifacts/zap-example`.
Example Authentication and Identity routes have isolated PHPUnit evidence and complete live operation coverage in the identity sequence.

## Static scan inputs

The security workflow pins the Semgrep image by digest.
`resources/security/semgrep/default.yml` contains all retrieved `p/default` rules with their metadata and licenses.
The adjacent manifest records the source, retrieval date, upstream and adjusted digests, rule count, scanner identity, and local adjustments.
The Slack webhook rule uses an equivalent regex exclusion for its dummy example.
This avoids storing a literal webhook that triggers push protection. Other webhook detection remains active.

Review the rule and image changes before replacing the snapshot.
Update the manifest after that review.
Run the static scan and inspect its findings before accepting the update.
Do not replace the local snapshot with a floating registry reference.

Production acceptance remains separate. See [identity acceptance](identity-acceptance.md).
