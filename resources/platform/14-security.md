# Authentication and Access Control

ExampleApi protects metadata and Example reads and writes with an API key.
UserApi and SystemAdminApi use separate JWT authentication realms.
Liveness and readiness remain public in each API.

## Consumer boundaries

- `X-Source-Id: example` selects ExampleApi. Its API-key contract remains unchanged.
- `X-Source-Id: user` selects UserApi and the trusted USER realm policy.
- `X-Source-Id: system-admin` selects SystemAdminApi and the trusted ADMIN realm policy.
- The bootstrap uses a fixed target map. A header cannot supply a filesystem path.
- Route policy selects JWT trust before claims are read.
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
Registration requires email, mobile, password, both verified contact IDs, and a shared context token. Login requires `identifier` and `password`.
The identifier accepts email or mobile. Resolve the Account before selecting its actor in the trusted realm.
Admin invitations use an existing Account and evidence. They do not introduce separate login identifiers.
Current identity and actor history responses expose Account email and mobile. Tokens do not contain contact fields.
Account closure releases both active contact keys. Actor removal preserves the Account contact reservations.

Contact ownership, actor approval, and password authentication establish separate facts.
Verify both email and mobile through the contact endpoints before User registration.
Both proofs must be less than 12 hours old when registration holds both evidence locks.
Codes expire after five minutes. Failed attempts, resend limits, and delivery identities are contact-specific.
Optional Admin verification preserves ownership, credential versions, and existing sessions.
See [contact verification](operations/contact-verification.md) for request fields, state transitions, quotas, and error contracts.

Password verification issues a session only after ownership, Account state, and actor approval checks pass.
Account.startSession owns current-version principal creation. Actor.assertSessionIdentity checks approval, removal, realm, and Account membership.
AuthenticationGate and LoginWorkflow do not inspect aggregate snapshots for these decisions.
LoginWorkflow and ContactAuthorizer call Credentials directly. LoginAuthentication selects expiry after the login transaction.
RegistrationCompletion creates identities and consumes proofs within the workflow transaction. Password hashing remains outside that transaction.
ContactDelivery executes verification before notification delivery. ContactCompletion records the operation result after delivery succeeds.
Both realms authenticate through `POST /auth/login` and return the `session` result.
Login does not create a challenge. TOTP enrollment and login-completion endpoints are absent.

Admin enrollment requires stored invitation and approval evidence.
An Admin cannot approve its own enrollment. Contact ownership remains a separate session gate.
First-Admin setup uses local root operator commands with separate invitation and approval evidence.
The manually approved initial Admin seeder attests ownership and records separate bootstrap invitation and approval evidence.
It remains separate from ordinary User registration. It creates no contact verification record.
Operator commands and the seeder never issue a token. See [operator procedures](operations/identity-operator.md).
Factor recovery and factor rewrapping actions are absent.

## Session state

Session issuance stores both `jti` and `sid` allowlist records before token delivery.
Redis records use realm and Account prefixes and include matching identity and credential version data.
Keys follow `identity:{REALM:accountId}:jti:tokenId`, `identity:{REALM:accountId}:sid:sessionId`, and `identity:{REALM:accountId}:blocked`.
Token and session values are RedisJSON objects. The token object contains `accountId`, `sub`, `sid`, `jti`, `authVersion`, `realm`, and `actorType`.
The session object omits `jti`. Redis does not store the signed JWT string.
`RedisTokenRegistry` uses the existing `RedisJsonInterface` library to execute Lua scripts through `raw('EVAL', ...)`.
One activation script checks conflicts and blocks, writes both objects with `JSON.SET`, and applies key-level `EXPIRE` after each write.
The remaining JWT lifetime is calculated in whole seconds from the Redis server clock.
The script then applies `EXPIREAT` to retain the JWT's exact expiration deadline. Reads do not renew expiry.
The script removes both new records if creation or expiry fails. No client can observe an intermediate activation state.
Verification reads JSON roots with `JSON.GET` and requires both records to match the token identity.
Existing string records remain readable until normal expiry. They are not rewritten or given a new TTL.
Account block markers remain ordinary string keys.
Authentication requires both records and current MySQL Account and actor state.
Token expiry and session expiry are bounded to the configured lifetime, between 60 and 3600 seconds.
The default lifetime is 900 seconds. The API provides no refresh-token flow.

Token revocation removes its `jti` record. Logout removes the active session and current token.
Account closure disables the Account and increments its authentication version before Redis revocation.
Current database state denies old tokens even if Redis revocation fails.
MySQL and Redis do not share one transaction.

Do not restore old identity allowlists after losing revocation evidence.
Invalidate both realms' affected allowlists and require login before reopening authentication traffic.
Current Account checks protect closure and authentication-version changes. They cannot recover a lost token-only revocation.
See [session recovery](operations/identity-session-recovery.md).

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
Ownership proof keys retain the `encryptionKey` configuration names for compatibility.
Retain each required proof key until pending codes and confirmed evidence recovery windows expire.
A confirmed record requires its code key through `verifiedAt + 12 hours`. Do not discard that key during another rotation.
New credential JSON contains only `passwordHash`. Existing credential JSON can retain unused factor fields.
No database schema change or automatic data cleanup is required.
Configuration and container definitions do not persist live credential values in caches.
Logs exclude submitted values and exception arguments.

Basis: [realm ADR](../adr/0037-user-and-system-admin-jwt-realms.md), [session ADR](../adr/0038-redis-token-allowlist-and-session-lifecycle.md), and [password authentication ADR](../adr/0049-password-authentication-without-totp.md).
