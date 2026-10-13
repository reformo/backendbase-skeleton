# ADR-0037: User and System Admin JWT Realms

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

User and System Admin APIs require separate authentication trust boundaries within one backend. A valid token for one realm must fail in the other.

The [decision report](references/2026-10-13-identity-access-decisions.md#realm-authentication-and-sessions) replaces [ADR-0029](0029-security-responsibility-after-identity-and-access-removal.md). The target realm middleware and token flow remain to be implemented.

- **Constraints:** Select policy from trusted routes. Separate issuer, audience, signing key, and actor type. Keep UUID identifiers as strings on the wire.

## Considered Options

### Option A: Separate USER and ADMIN JWT policies

- **Pros:** Route policy determines the expected credential independently of token claims. Cross-realm acceptance can be tested directly.
- **Cons / Risks:** Two signing policies and their configuration need maintenance. Key rotation and storage require operational policy.
- **Estimated Cost / Effort:** Medium

### Option B: One shared JWT trust policy with claim-selected realm

- **Pros:** Token issuance and key configuration require fewer policy definitions.
- **Cons / Risks:** Unverified claims can influence trust selection. Shared credentials weaken the required realm boundary.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report explicitly requires mutually rejecting USER and ADMIN realms. Route configuration must determine trust before token verification.

## Consequences

- **Positive:** Authentication validates signature, allowed algorithm, issuer, audience, expiration, not-before time, and actor type before constructing a principal.
- **Negative / Trade-offs:** JWT validity alone is insufficient. [Redis session checks](0038-redis-token-allowlist-and-session-lifecycle.md) and application authorization remain required.

## Implementation & Validation

- **Rollout Steps:** Declare realm policy on trusted routes. Validate tokens and Redis identity consistency. Pass verified account, actor, realm, and session identity to Application.
- **Verification:** Reject USER tokens on ADMIN routes and ADMIN tokens on USER routes. Test wrong keys, algorithms, claims, expiry, session mismatches, and denied privileges.
- **Review Date:** Review when realm scope, key lifecycle, public authentication contracts, or issuer ownership changes.
