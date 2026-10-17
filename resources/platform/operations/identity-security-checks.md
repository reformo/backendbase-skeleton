# Repeatable identity security checks

Use dedicated disposable MySQL and Redis services.
The HTTP suite creates and removes one uniquely named MySQL database.
It leaves short-lived Redis verification counters and session records in the disposable Redis service.
Do not run this suite against shared application services.

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
The fixture database, temporary server, and scan containers are removed when the run ends.

## Coverage

The deterministic HTTP sequence checks all 28 current identity operations.
It verifies successful registration and authentication, realm isolation, approval, removal, restoration, token expiry, revocation, logout, and Account closure.
It also checks missing credentials, invalid and oversized field input, contact confirmation, and resend cooldown rejection.
New OpenAPI operations without a recorded HTTP result fail the suite.

The two authenticated ZAP scans target `GET /identity` and `GET /_status` in separate realms.
Fresh sessions must access the protected route before and after each scan.
Mutation routes use the deterministic sequence because scanning them can revoke or close the scan identity.
Readiness uses a deterministic check that requires MySQL and Redis readiness while unprovisioned dependencies remain unavailable.
This scope does not claim exhaustive payload fuzzing of every mutation.

The suite writes operation results to `artifacts/identity-http.json`.
It writes sanitized ZAP reports and scan scope to `artifacts/zap-identity`.
The security workflow retains these artifacts for 30 days.
ZAP authentication tokens are removed from retained reports.

## ExampleApi checks

The security workflow configures temporary USER and ADMIN signing keys for its disposable services.
It seeds a fixture Account and obtains a USER session through ExampleApi login.
The Bruno `example` folder and Example write fixtures send both the API key and bearer token.
Boundary checks reject writes without a bearer token.
The Example ZAP contract excludes Authentication, Identity, and readiness operations.
It retains the six Example operations, metadata, and liveness. The scan supplies the API key and USER bearer token.
The workflow redacts both credentials before uploading reports from `artifacts/zap-example`.
Example Authentication and Identity routes have isolated PHPUnit evidence. The live identity sequence continues to cover UserApi and SystemAdminApi.

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
