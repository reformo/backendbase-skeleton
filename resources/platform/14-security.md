# Authentication and Access Control

Read the [IdentityAndAccess capability guide](../docs/bounded-contexts/identity-and-access.html) for business explanations, workflows, configuration, and support decisions.

ExampleApi requires an API key for metadata, Authentication, Identity, and Example operations.
Example and Identity protected groups also require the existing USER bearer authentication.
`getExampleByCriteria` requires no bearer token. ExampleApi and UserApi share Accounts, signing settings, sessions, and revocation state.
UserApi and SystemAdminApi use separate JWT authentication realms.
Liveness and readiness remain public in each API.

Nginx applies a shared general limit of 30 requests per second per client IP with a burst allowance of 60.
Public API health and preflight requests count toward that limit. Restricted internal readiness remains available without a rate counter.
Gateway rejections precede PHP authentication and replay. Contact verification quotas remain in IdentityAndAccess.
See [HTTP rate limiting](operations/http-rate-limiting.md) for client address trust, instance scope, and deployment configuration.

## Inbound API boundaries

- `X-Source-Id: example` selects ExampleApi. Its Authentication, Identity, and protected Example groups use the trusted USER realm.
- `X-Source-Id: user` selects UserApi and the trusted USER realm policy.
- `X-Source-Id: system-admin` selects SystemAdminApi and the trusted ADMIN realm policy.
- The bootstrap uses a fixed target map. A header cannot supply a filesystem path.
- Each Authentication, Identity, Administration, and protected Example group defines its realm once through `RealmPolicy`.
- Protected groups add `Idempotency`, `RealmAuthentication`, then `RealmPolicy`. Slim executes `RealmPolicy` first, before token validation.
- `RoutePolicy` supplies endpoint actions. `ContactRoutePolicy` supplies contact settings. Neither policy changes the group realm.
- Authentication groups apply `RealmPolicy` and write replay through `Idempotency` at the group level. ExampleApi still requires its API key. Health and preflight routes require no credentials.
- USER and ADMIN use distinct issuers, audiences, and signing keys.
- Signing uses HS256 with configured 32-byte keys and explicit key identifiers.
- Verification checks signature, algorithm, issuer, audience, dates, realm, actor type, and canonical identifiers.

## JWT contract

`sub` identifies the actor. `accountId` identifies the global Account.
`realm` is `USER` or `ADMIN`. `actorType` is `User` or `SystemAdmin`, respectively.
`authVersion` is the Account authentication version as an integer.
`jti` and `sid` are canonical UUID strings for token and session identity.
Tokens also contain `iss`, `aud`, `iat`, `nbf`, and `exp`.
The header contains `alg: HS256`, `typ: JWT`, and the configured `kid`.
Do not replace these fields with the architecture report's conceptual `actor_id` or `actor_type` examples.

Missing or invalid credentials return a safe 401 response.
Missing authentication state or a Redis outage returns a safe 503 response.
Identity uniqueness and state conflicts return a safe 409 response.

## Authentication facts

Global Accounts own required email, required mobile, password credentials, and optional first and last names.
Registration accepts nullable `firstName` and `lastName`. Names contain 1 to 160 UTF-8 characters when supplied.
Reject empty names, surrounding spaces, and control characters. Preserve accepted text without normalization.
Current identity and actor history return both names, including null values. Names do not appear in tokens.
Each Account can have at most one User and one Admin, including removed actor rows.
Account email and mobile are each globally unique among active Accounts. Actors have no username.
ASCII normalization lowercases accepted email addresses. Mobile numbers use E.164 format: a plus sign and 2 to 15 digits.
Reject whitespace, domestic formatting, extensions, non-ASCII email, and non-ASCII digits. Do not infer a country code.
Registration requires email, mobile, and password. The context token is required when either configured contact proof is required. Contact identifier fields are rejected. Login requires `identifier` and `password`.
The identifier accepts email or mobile. Resolve the Account before selecting its actor in the trusted realm.
Admin invitations use an existing Account and evidence. They do not introduce separate login identifiers.
Current identity and actor history responses expose Account email and mobile. Tokens do not contain contact fields.
Account closure releases both active contact keys. Actor removal preserves the Account contact reservations.

Contact ownership, actor approval, and password authentication establish separate facts.
Account `state.registrationAccepted` records successful User registration under the policy in effect at creation.
Account `state.ownershipVerified` records ownership attested at registration or trusted Admin bootstrap. Both flags start as `false`.
Registration sets acceptance after all configured proof requirements pass. It verifies ownership only when evidence is consumed.
User login and session validation require acceptance. Admin authentication and enrollment retain the ownership requirement.
Both realms also require an enabled Account, the current authentication version, and the applicable actor checks.
Legacy state without acceptance derives that value from validated ownership. Reads do not rewrite records or invalidate existing sessions.
The default registration policy requires mobile proof and permits optional email proof. Each requirement is independently configurable.
Configuration changes affect new registrations. They do not recheck accepted Accounts or alter token claims.
Registration resolves evidence by submitted values and context. It locks email first, then mobile, before checking the 12-hour evidence window.
Matching fresh optional evidence is consumed when present. Missing or unusable optional evidence does not block registration.
Authenticated Users can verify their stored email through `/identity/contact-verifications` with a separate Account-bound purpose.
Email verification preserves ownership and sessions. It cannot authorize registration or change the stored email.
Codes expire after five minutes. Failed attempts, resend limits, and delivery identities are contact-specific.
Optional Admin verification preserves ownership, credential versions, and existing sessions.
See [contact verification](operations/contact-verification.md) for request fields, state transitions, quotas, and error contracts.

Password verification issues a session after the realm-specific Account gate, Account state, and actor approval checks pass.
Account.startSession owns current-version principal creation. Actor.assertSessionIdentity checks approval, removal, realm, and Account membership.
AuthenticationGate and LoginWorkflow do not inspect aggregate snapshots for these decisions.
Account.assertEligible owns realm-specific acceptance and ownership checks. Account.contactFor selects the permitted stored contact.
Actor.assertContactOwner checks contact membership. SystemAdmin.assertPendingApproval checks the locked first-Admin state.
LoginWorkflow and ContactAuthorizer call Credentials directly. LoginAuthentication selects expiry after the login transaction.
RegistrationWorkflow hashes the password through Credentials before RegistrationCompletion opens the identity transaction.
RegistrationCompletion checks uniqueness, creates identities, stores credentials, and consumes proofs within that transaction.
Hashing failure opens no transaction. Credential failure rolls back identity creation and proof consumption.
ContactDelivery executes verification before notification delivery. ContactCompletion records the operation result after delivery succeeds.
Both realms authenticate through `POST /auth/login` and return the `session` result. ExampleApi exposes the same User contact, registration, login, identity, token, session, and Account endpoints as UserApi.
ExampleApi uses its own controllers and the existing identity input, dispatch, policy, and application services. Its identity response key remains `user`.
Login does not create a challenge. TOTP enrollment and login-completion endpoints are absent.

Admin enrollment requires stored invitation and approval evidence.
An Admin cannot approve its own enrollment. Contact ownership remains a separate session gate.
First-Admin setup uses local root operator commands with separate invitation and approval evidence.
The evidence remains in Admin state. Operator names and action timestamps have no separate persistence table.
ApplyOperatorActionHandler calls OperatorRuntime with typed OperatorInput. OperatorRuntime supplies the injected clock and calls OperatorWorkflow.
The handler records the result only after the workflow returns. Preview writes no Admin record. Failed actions publish no result.
Operator setup and manual seeding share a database-scoped MySQL advisory lock before starting their transactions.
Only the sole pending Admin can receive operator approval. Removed Admin rows prevent another first-Admin invitation.
The manually approved initial Admin seeder attests ownership and records separate bootstrap invitation and approval evidence.
It remains separate from ordinary User registration. It creates no contact verification record.
Operator commands and the seeder never issue a token. See [operator procedures](operations/identity-operator.md).
Factor recovery and factor rewrapping actions are absent.

Identity state primitives reject missing, mistyped, and unsupported stored fields before domain reconstitution.
Actor history reads and repeat Admin seeding use the same state types.
State serialization is an internal storage contract. Never expose Contact code hashes through logs or public responses.

## Session state

Session issuance stores both `jti` and `sid` allowlist records before token delivery.
Redis keys use the shared cluster slot `identity:{REALM:accountId}`.
Token objects retain `accountId`, `sub`, `sid`, `jti`, `authVersion`, `realm`, and `actorType`.
Version 2 session objects also store current `jti`, policy, creation time, last activity, maximum expiry, and a refresh hash.
The signed JWT and raw refresh secret are absent from Redis.

USER defaults are token 900 seconds, idle 86400 seconds, and maximum 604800 seconds.
ADMIN defaults are token 300 seconds, idle 1800 seconds, and maximum 28800 seconds.
Six settings under `identity.user` and `identity.admin` control new sessions. Existing sessions retain their stored policy.
Access-token lifetime permits 60–3600 seconds. Idle requires at least 60 seconds.
Maximum must cover idle and cannot exceed 31536000 seconds.

Lua activation writes both RedisJSON objects atomically and applies `EXPIRE` and `EXPIREAT` with their separate deadlines.
Access tokens expire at their issue deadline, capped by the fixed session maximum.
Sessions expire at `min(lastUsedAt + idleTimeoutSeconds, maximumExpiresAt)`.
Successful protected authentication extends idle only after current database eligibility passes.
Refresh credential inspection and replay do not rotate credentials or extend idle.
Existing string and version 1 JSON records retain their original expiry. They cannot renew before the next password login.

`POST /auth/refresh` authenticates the session and current Account and actor before HTTP replay.
Each rotation replaces the refresh hash and creates a new access-token record.
The session identifier, creation time, maximum deadline, and policy remain unchanged.
Earlier access-token records remain valid until their expiry while the session remains active.
Consumed digest markers use `used-refresh:sessionId:digest` keys through the maximum deadline.
Unknown secrets and wrong realms deny access without revocation.
A known consumed token used for a new operation revokes the session.

Browser clients select cookie transport. Cookies use a realm-specific `__Host-` name, Secure, HttpOnly, SameSite=Strict, and Path=/.
Cookie login and renewal require an exact configured Origin before replay. Wildcard and opaque origins are rejected.
Matching configured origins receive credentialed CORS responses. Use HTTPS and same-site browser and API hosts.
Mobile clients use body transport and operating system secure storage. Browser local storage must not contain refresh secrets.
The client helper renews on demand and serializes browser tabs through Web Locks. It has no background renewal timer.

Matching renewal retries return the same encrypted response and cookie.
A response guard rejects an obsolete result with 409 `identity/conflict` before sending an earlier credential.
The helper then attempts one renewal with the current credential.
A revoked, expired, or ineligible session cannot replay a successful renewal.

Token revocation removes its `jti` record. Logout removes the session and current token and clears its browser cookie.
Account closure disables the Account and increments its authentication version before Redis revocation.
Current database state denies old access and refresh credentials even if Redis revocation fails.
MySQL and Redis do not share one transaction.

Do not restore old identity allowlists or consumed refresh credentials after losing revocation evidence.
Invalidate affected namespaces in both realms before reopening authentication traffic. Require another login.
Current Account checks cannot recover a lost token-only revocation.
See [renewable sessions](../adr/0053-renewable-realm-sessions.md), the [client guide](../guides/1-identity-and-access.html#session-renewal), and [session recovery](operations/identity-session-recovery.md).

## Internal policy

Application handlers check current identity and actor authority before protected mutations.
Reload persisted actor state under Account-first locks after a target reference lookup.
A concurrently removed Admin cannot use its earlier managed state to restore itself.
Admin actor history uses stored values and includes removed rows.
Actor restoration requires an active Account and passes the same database uniqueness constraints.

Shared `AccessControl` remains the internal privilege contract.
Internal `QueueGreeting` requires `example.add`. Its caller supplies the access-control implementation.
Greeting HTTP endpoints remain absent.

## Secret handling

Keys have no production defaults. Runtime configuration selects a managed provider and controlled injection.
Stored passwords use Argon2id. Ownership proofs use keyed SHA-256 hashes.
Signing-key and ownership-proof-key rotation support an active key and a distinct previous key identifier.
Contact proof keys use `contact-proof-key`, `contact-proof-key-id`, and the corresponding previous-key settings.
Environment variables use `CONTACT_PROOF_KEY` names. The former `ENCRYPTION_KEY` environment names remain fallback aliases during rollout.
Canonical names take precedence. Retain both active and previous key values while pending proof and recovery windows require them.
Retain each required proof key until pending codes and confirmed evidence recovery windows expire.
A confirmed record requires its code key through `verifiedAt + 12 hours`. Do not discard that key during another rotation.
Accounts store the Argon2id password hash directly in `account.password_hash`, a required `VARCHAR(255)` column.
The credential store reads and writes the hash as a string. Account ownership, state checks, and locking remain required.
The consolidated installation migration creates this column directly. Existing databases with credential JSON require separate schema and data adoption.
No automatic data conversion or cleanup runs.
Configuration and container definitions do not persist live credential values in caches.
Logs exclude submitted values and exception arguments.

Basis: [realm ADR](../adr/0037-user-and-system-admin-jwt-realms.md), [session ADR](../adr/0038-redis-token-allowlist-and-session-lifecycle.md), and [password authentication ADR](../adr/0049-password-authentication-without-totp.md).

## HTTP replay protection

All write routes require `Idempotency-Key`. Protected replay follows current API-key and bearer authentication.
Revoked sessions remain rejected. Login replay returns the original token without renewing or restoring its session.
Dedicated `BACKENDBASE_IDEMPOTENCY_KEY` protects body fingerprints and encrypted responses. Raw passwords and tokens are absent from claim metadata.
See [HTTP idempotency](operations/http-idempotency.md) for retention and key replacement constraints.
