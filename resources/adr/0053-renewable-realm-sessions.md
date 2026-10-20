# ADR-0053: Renewable Realm Sessions

- **Status:** Accepted
- **Date:** 2026-10-20
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Access tokens and sessions previously shared one expiry. A short token lifetime required another password login after expiry.
Browser and mobile clients need renewal while the session remains eligible.
USER and ADMIN require independent access-token, idle, and maximum session durations.

## Considered Options

### Option A: Rotating refresh credentials with separate session deadlines

- **Pros:** Short access tokens remain usable with longer sessions. Both client types use one renewal service.
- **Cons / Risks:** Clients must store rotated credentials and serialize renewal. Redis state and response replay need coordinated validation.

### Option B: Longer access tokens without renewal

- **Pros:** The existing client contract needs fewer changes.
- **Cons / Risks:** Token and session expiry remain coupled. It does not provide independent idle and maximum limits.

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Separate credential expiry from session eligibility. Keep current database and Redis checks before authentication and replay.
- **Defaults:** USER uses a 900-second token, 86400-second idle timeout, and 604800-second maximum. ADMIN uses 300, 1800, and 28800 seconds.
- **Configuration:** Replace the shared lifetime with six settings under `identity.user` and `identity.admin`. Store the policy on each session.
- **Boundaries:** Tokens permit 60–3600 seconds. Idle requires at least 60 seconds. Maximum covers idle and cannot exceed 31536000 seconds.
- **Renewal:** Add `POST /auth/refresh` to each API. Require `Idempotency-Key`. Authenticate the refresh credential and current identity before replay.
- **Rotation:** Atomically store a new access-token record and refresh hash. Preserve session ID, creation time, policy, and maximum expiry.
- **Activity:** Successful protected authentication extends idle after current database checks. Access-token and maximum expiry remain fixed.
- **Reuse:** Retain consumed refresh hashes until maximum expiry. A consumed credential used for a new operation revokes the session.
- **Browser:** Select `refreshTokenTransport: cookie`. Use a Secure, HttpOnly, SameSite=Strict host cookie and an exact configured Origin.
- **Mobile:** Select `body`, the default transport. Return the refresh token in JSON for operating system secure storage.
- **Replay:** Encrypt Set-Cookie with the stored response. Reject obsolete renewal results with 409 before returning an earlier credential.
- **Client:** Renew on demand before access expiry. Serialize requests and browser tabs. Keep pending renewal keys after uncertain responses.

## Consequences

- Existing sessions retain their previous deadline. They cannot renew before their next password login.
- Existing renewable sessions retain their original policy after configuration changes.
- Browser and API hosts must use HTTPS and belong to the same site for Strict cookies.
- Native clients must save the replacement before using the access token. An interrupted save can require another login after process restart.
- The client helper does not use background renewal. Inactive clients reach idle expiry.
- Earlier access tokens remain valid until token expiry while the session remains active.
- Logout removes session authority. Account closure and actor rejection remain effective even when Redis revocation fails.
- Rolling back to the former session schema requires controlled logout.
- This decision updates ADR-0038 expiry and storage details. Realm, revocation, and recovery requirements remain in effect.

## Implementation & Validation

- **Implementation:** `Domain/Sessions`, `Application/Sessions`, Redis session scripts, and shared HTTP session adapters.
- **Contracts:** Source and merged OpenAPI, Bruno requests, and the [IdentityAndAccess guide](../guides/1-identity-and-access.html#session-renewal).
- **Verification:** Domain deadline tests, real application workflows, all three HTTP APIs, cookie and replay tests, native Redis scripts, and client helper tests.
- **Recovery:** Follow [session recovery](../platform/operations/identity-session-recovery.md). Never restore consumed credentials after losing revocation evidence.
- **Basis:** [RFC 9700 refresh rotation](https://www.rfc-editor.org/rfc/rfc9700.html#section-4.14.2) and [OWASP browser storage guidance](https://cheatsheetseries.owasp.org/cheatsheets/HTML5_Security_Cheat_Sheet.html#local-storage).
- **Review Date:** Review when session policy, browser site boundaries, credential storage, or replay retention changes.
