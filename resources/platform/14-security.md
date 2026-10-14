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

Missing or invalid credentials return a safe 401 response.
Missing authentication state or a Redis outage returns a safe 503 response.
Identity uniqueness and state conflicts return a safe 409 response.

## Authentication facts

Global Accounts own email, password credentials, and TOTP factors.
Each Account can have at most one User and one Admin, including removed actor rows.
Actor login names are unique within each realm. Account email has its own active uniqueness rule.
ASCII normalization lowercases accepted login names and email addresses. Unicode login names and email addresses are rejected.

Contact ownership, actor approval, and MFA establish separate facts.
Registration sends a six-digit ownership proof through the notification port.
Ownership proofs expire after five minutes and permit at most five attempts.
Resend requires the password and invalidates previous ownership proofs.

Password verification creates a login challenge. It does not deliver a token.
Both realms require TOTP before session issuance.
TOTP uses six digits, SHA1, 30-second periods, and one step of clock tolerance.
Accepted steps cannot be reused. Enrollment consumes a step, so another login requires a later valid step.
Five failed MFA proofs block the Account for five minutes across different challenges.

Admin enrollment requires stored invitation and approval evidence.
An Admin cannot approve its own enrollment. Contact ownership remains a separate session gate.
First-Admin setup and lost-factor recovery use local root operator commands with recorded evidence.
First-Admin invitation and approval use separate steps and references. Recovery requires separate request and approval references.
Recovery clears factors, increments the credential version, and removes contact ownership verification.
The existing password is then required to request a new ownership proof and enroll a new TOTP factor.
Operator commands never issue a token. See [operator procedures](operations/identity-operator.md).
Active factors cannot be replaced through ordinary enrollment.

## Session state

Session issuance stores both `jti` and `sid` allowlist records before token delivery.
Redis records use realm and Account prefixes and include matching identity and credential version data.
Authentication requires both records and current MySQL Account and actor state.
Token expiry and session expiry are bounded to the configured lifetime, between 60 and 3600 seconds.
The default lifetime is 900 seconds. The API provides no refresh-token flow.

Token revocation removes its `jti` record. Logout removes the active session and current token.
Account closure disables the Account and increments its authentication version before Redis revocation.
Current database state denies old tokens even if Redis revocation fails.
MySQL and Redis do not share one transaction.

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
Factor secrets use authenticated Sodium encryption. Stored passwords use Argon2id.
Signing-key and factor-key rotation support an active key and a distinct previous key identifier.
Configuration and container definitions do not persist live credential values in caches.
Logs exclude submitted values and exception arguments.

Basis: [realm ADR](../adr/0037-user-and-system-admin-jwt-realms.md), [session ADR](../adr/0038-redis-token-allowlist-and-session-lifecycle.md), and [authentication challenge ADR](../adr/0046-authentication-challenges-and-mfa-gated-sessions.md).
