# ADR-0029: Security Responsibility after IdentityAndAccess Removal

- **Status:** Superseded by ADR-0037
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The built-in IdentityAndAccess module and bearer-token flow have been removed. Consumer APIs still need credential validation and explicit anonymous access policy.

This record preserves the previous implementation baseline. The [final decision report](references/2026-10-13-identity-access-decisions.md#realm-authentication-and-sessions) replaces its API-key-only identity boundary with [separate JWT realms](0037-user-and-system-admin-jwt-realms.md), a [single identity context](0036-single-identity-and-access-context-and-global-accounts.md), and [Redis session validation](0038-redis-token-allowlist-and-session-lifecycle.md).

The replacement is an accepted target decision. It does not establish that the removed context, authentication routes, or token services have been restored.

[Security guidance](../platform/14-security.md) retains API-key validation and the framework-independent AccessControl application contract.

- **Constraints:** An API key identifies an authorized consumer credential. It does not establish an end-user identity. Keep privilege checks at the application boundary where required.

## Considered Options

### Option A: API-key-protected APIs with explicit application access policy

- **Pros:** Consumer protection remains available without an embedded identity module. Applications can supply AccessControl when a use case requires privileges.
- **Cons / Risks:** End-user identity and account lifecycle require a separate project decision. API-key compromise grants the configured consumer access.
- **Estimated Cost / Effort:** Medium

### Option B: Reintroduce built-in account and bearer-token services

- **Pros:** The platform could own end-user authentication and identity lifecycle.
- **Cons / Risks:** Identity storage, token management, migrations, and public authentication contracts return to platform scope.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The current platform boundary excludes built-in identity management while retaining consumer credentials and application-owned authorization contracts.

## Consequences

- **Positive:** Missing or invalid API keys fail before controller dispatch. Liveness and readiness can remain public.
- **Negative / Trade-offs:** Anonymous exceptions need explicit review. Existing public path prefixes and documented headers have runtime alignment limits.

## Implementation & Validation

- **Rollout Steps:** Use ADR-0037 and its referenced identity and session decisions for future authentication work. Preserve application-owned privilege checks.
- **Verification:** Verify the replacement through cross-realm, session, and application policy tests. Existing API-key checks remain current implementation evidence only.
- **Review Date:** Review future identity and consumer authentication changes under ADR-0037.
