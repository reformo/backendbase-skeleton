# ADR-0029: Security Responsibility after IdentityAndAccess Removal

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The built-in IdentityAndAccess module and bearer-token flow have been removed. Consumer APIs still need credential validation and explicit anonymous access policy.

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

- **Rollout Steps:** Protect consumer operations with API-key middleware by default. Declare anonymous operations. Supply typed AccessControl only where application privilege policy requires it.
- **Verification:** Test [API-key middleware](../../src/Backendbase/Infrastructure/Adapters/Http/Middleware/ValidateApiKey.php), anonymous routes, safe errors, and allowed and denied application policy paths.
- **Review Date:** Review before introducing user identity, tenant policy, or a new consumer credential model.
