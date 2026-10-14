# ADR-0038: Redis Token Allowlist and Session Lifecycle

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

A correctly signed token can remain cryptographically valid after logout or account closure. Authentication therefore requires current token and session state.

The [decision report](references/2026-10-13-identity-access-decisions.md#realm-authentication-and-sessions) selects Redis 8 allowlists. Atomic Redis activation records token and session identities with expiry. Verification requires both entries and current database identity state. Token revocation, logout, and Account closure invalidate the corresponding authority.

- **Constraints:** Fail closed on Redis errors. Distinguish access-token jti from session sid. Preserve trusted current account status across MySQL and Redis boundaries.

## Considered Options

### Option A: Active token and session records checked on each authenticated request

- **Pros:** Token and session revocation can deny access before token expiry. Realm-prefixed records separate USER and ADMIN state.
- **Cons / Risks:** Authentication depends on Redis availability and consistent state. MySQL and Redis cannot commit as one database transaction.
- **Estimated Cost / Effort:** High

### Option B: Cryptographic JWT validation until expiry

- **Pros:** Normal authentication needs no Redis state lookup.
- **Cons / Risks:** Logout and session revocation cannot immediately invalidate a signed access token.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The approved lifecycle requires both an active jti record and an active sid record, with matching account, actor, session, and realm identities.

## Consequences

- **Positive:** Successful issuance records state and expiry before returning the token. Session revocation rejects all tokens for that session.
- **Negative / Trade-offs:** Account closure needs a trusted status or version gate together with Redis revocation. Redis outages produce safe service-unavailable errors without bypass.

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Define realm-prefixed access and session records. Store state before token delivery. Implement token, session, and account revocation coordination.
- **Verification:** Use prepared Redis 8 tests for expiry, logout, revocation, identity mismatch, failed issuance, dependency outage, and account closure races.
- **Review Date:** Review when token lifetime, Redis recovery, account revocation, or session schema changes.
