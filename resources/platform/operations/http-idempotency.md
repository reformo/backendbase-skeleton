# HTTP Idempotency

Read the [HTML capability guide](../../docs/http-idempotency.html) for examples, response decisions, configuration, and recovery guidance for mixed audiences.
Its [payment walkthrough](../../docs/http-idempotency.html#payment-example) includes PHP examples for route registration, middleware replay, business transactions, response mapping, and provider-status recovery.
The payment types are illustrative. The replay middleware excerpt reflects the current implementation.

All 41 documented POST, PATCH, and DELETE operations require `Idempotency-Key`.
The shared middleware also supports PUT when a named write route is added.
Authentication, registration, contact request, confirmation, resend, session changes, administration, and Example writes use this policy.
GET, HEAD, and OPTIONS need no key. `X-Request-Id` remains an optional trace identifier.

## Client contract

Generate an unpredictable key for each intended operation. A UUID is suitable.
Supply exactly one header value with 1 to 255 characters.
The first character must be an ASCII letter or digit. Remaining characters can also include `.`, `_`, `:`, and `-`.
The current wire format uses an unquoted value.

Reuse the key for retries of that operation.
Keep the HTTP method, path, query string, exact body bytes, credentials, and representation headers unchanged.
The fingerprint includes `Content-Type`, `Accept`, `Accept-Language`, `The-Timezone-IANA`, `Authorization`, and `Backendbase-Api-Key`.
JSON whitespace and query ordering are significant. `X-Request-Id` can change for each attempt.
A corrected payload or a new intended operation needs a new key.

Keys are scoped to the operation ID and authenticated realm, Account, and actor.
Operation IDs separate the three inbound APIs. Public authentication operations use an anonymous scope within their operation ID.
The body and credential fingerprint prevents a public key from returning a different caller's response.
Keep operation IDs stable while retained claims exist. Renaming an operation changes its key scope.

| Condition | Response |
| --- | --- |
| Missing, repeated, malformed, or oversized key | 400 before the operation starts |
| First accepted claim | Execute once and store the response |
| Completed matching claim | Return the original status, body, and selected headers |
| Same scoped key with different request content | 422 with `idempotency/request-mismatch` |
| Concurrent request or interrupted attempt without a stored response | 409 with `idempotency/request-outstanding` |
| Claim, response storage, encryption, or decryption failure | 503 without starting another operation |

Completed responses remain replayable for 24 hours after completion. Reading a response does not renew this period.
After expiry, the same key can start another operation. Do not retry an uncertain operation beyond this window automatically.
Stored responses include failures raised during operation execution, including 400, 429, 500, and 503.
A stored failure returns unchanged on retry. Check the operation outcome before starting another attempt with a new key.
Missing-key rejection, authentication rejection, and body-size rejection occur before a claim and are not stored.

The stored headers are `Content-Type`, `Cache-Control`, `Content-Language`, `Backendbase-Insert-Id`, `Location`, `Retry-After`, and `Set-Cookie`.
Trace, transport, and cross-origin response headers are not replayed. Cookie values are encrypted with the stored response.
Response bodies must fit within 1 MiB. Current write responses are small JSON objects or empty bodies.
An oversized or unserializable response leaves its claim unresolved and returns 503.

## Authentication and sessions

API-key and bearer authentication run before replay on protected routes.
An expired or revoked token still returns 401, including a retry after successful logout, token revocation, or Account closure.
A login replay returns the original token. It does not renew expiry, restore an allowlist record, or create another session.
A fresh login needs a new key. A previously issued token can already be expired or revoked when its response is replayed.

Refresh routes authenticate the current refresh credential, live session, and database eligibility before replay.
Their caller scope also includes the session identifier. A new operation needs a new key and the current refresh credential.
A matching retry can use the consumed credential or the replacement cookie while the session remains eligible.
The fingerprint keeps the exact body. Browser cookie rotation does not change the fingerprint.
A response guard returns 409 `identity/conflict` when a later rotation replaced the cached result.
This prevents an earlier response from replacing a newer cookie. No obsolete credential is returned.
The client helper retries that conflict once with the current credential and a new key.
A consumed refresh token used for another intended operation revokes the session.
A revoked, expired, or ineligible session cannot replay renewal.

Contact retries do not resend a notification, consume a confirmation again, or renew its stored verification time.
Existing application checks still run during the first execution.
A provider timeout can leave an uncertain external effect. Inspect provider and application evidence before any new operation.

## Architecture and failure handling

`Infrastructure/Adapters/Http/Idempotency` validates the header, identifies the request, and maps stored responses.
`Shared/Idempotency` defines the storage and encryption ports and validated request identity.
The Doctrine adapter owns `http_request_replay`. The security adapter uses authenticated Sodium encryption and keyed request fingerprints.
Raw request bodies, passwords, credentials, and idempotency keys are not stored.
Responses containing tokens or personal data are encrypted. Claims contain only digests and timestamps outside the ciphertext.

The store commits a unique claim before calling the application operation.
Application handlers keep their existing transactions. HTTP middleware does not wrap them in a database transaction.
Identity operations can therefore retain their transaction retries and separate Redis and notification effects.
The stored response is committed after the operation finishes.
A crash between these commits leaves a durable unresolved claim. The server blocks another execution under that key.
Unresolved claims have no automatic expiry or takeover. This design does not claim exactly-once external delivery.

A known exception is translated through the existing safe HTTP error handler and stored as a response.
Failure to store that response leaves the claim unresolved. The client must retain its original key.
Do not delete a claim or generate a new key merely because a request timed out.

## Configuration and rollout

Set `BACKENDBASE_IDEMPOTENCY_KEY` to a dedicated base64-encoded 32-byte secret on every API instance.
The public value in `.env.example` is for local development only.
Keep this secret separate from signing keys and contact proof keys. Preserve it with encrypted backups.
There is no fallback key. Invalid configuration makes write replay unavailable.
Changing the secret invalidates fingerprints and ciphertext for retained responses. Online key rotation is not implemented.
Drain writes, reconcile unresolved claims, and expire retained completed responses before an authorized key replacement.
Never discard unresolved evidence to make a key replacement succeed.

Review `Version20261018000000` before applying it to an identified database.
The migration creates only `http_request_replay`, its primary key, and its expiry index. It changes no business data.
MySQL DDL is non-transactional. An existing table makes creation fail; inspect its schema before recovery.
The reverse migration refuses to remove duplicate-protection evidence.
The release target includes this migration. Existing release compatibility restrictions remain in effect.
Deploy the secret and reviewed schema before activating the middleware.
Update clients before requiring this header. Stop writes during mixed-version activation because older nodes do not enforce this contract.
Clear route caches during activation. Local edits do not apply the migration or deploy the configuration.

## Retention and recovery

Run `bin/backendbase idempotency:cleanup` on an authorized target to remove up to 500 expired completed responses.
Schedule bounded repeated runs for the deployment's request volume. No schedule is installed automatically.
The cleanup command never removes unresolved claims. Accessing an expired completed key also removes that one record before claiming it again.

Monitor unresolved claims by `created_at` and identify their original request through caller-held keys and operational evidence.
For an uncertain outcome, keep the claim blocked and inspect business records, outbox records, Redis state, and provider evidence.
A separately authorized recovery must prove the outcome before completing or releasing an exact claim.
Do not reset timestamps, bulk-delete claims, or repeat external effects to infer success.
Restore claim evidence consistently with business data. Restoring older claims or losing claims can permit duplicate operations.

## Indexes and verification

The `request_hash` primary key covers claim, lookup, completion, and single-key expiry checks.
The `http_request_replay_expiry_idx (expires_at)` index covers ordered cleanup selection.
Cleanup deletes recheck expiry and completed response state, so a concurrent replacement claim is retained.
No ORM entity is needed for this infrastructure table. Doctrine's mapped-table filter excludes it from business-schema diffs.

SQLite tests prove duplicate handling, expiry, cleanup, rollback boundaries, and two-connection visibility.
HTTP tests cover login, registration, notifications, tracing, credential checks, concurrent requests, and response-storage failure.
The opt-in MySQL tests verify the migration, independent connections, HTTP replay, expiry, cleanup, and index plans on a prepared target.
Offline SQL review and SQLite evidence do not establish native MySQL execution or production query plans.

The 18 October 2026 isolated run verifies native execution on MySQL 8.4.11 and Redis 8.10.2.
All 63 previously skipped cases and the added native HTTP replay case pass within the 1,422-test suite, with zero skips.
That run verified fresh-installation dry-run and execution through the former six-migration history. Existing replay tables reject repeated creation.
The current four-migration history passed [native MySQL verification on 19 October 2026](migration-consolidation.md#native-verification-on-19-october-2026).
Replay lookup uses `PRIMARY`. Ordered cleanup uses `http_request_replay_expiry_idx` on 1,000 generated rows.
Live identity requests verify 33 unchanged response replays, 21 changed-body rejections, and six revoked-credential rejections.
All generated services are removed. See [recorded native evidence](identity-acceptance.md#latest-isolated-regression-evidence).
