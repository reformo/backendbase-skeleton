# ADR-0031: Stable Public Error Contracts

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Clients need stable error identifiers and statuses. Internal exceptions need diagnostic detail without exposing implementation or sensitive data in public responses.

[Error guidance](../platform/16-errors-observability.md) assigns public mappings to [DomainErrorProblemDetailsMapper](../../src/Backendbase/Infrastructure/Adapters/Http/DomainErrorProblemDetailsMapper.php).

- **Constraints:** Keep domain errors independent of HTTP. Preserve released type, code, title, and status values. Hide internal details outside development.

## Considered Options

### Option A: Central boundary mapping to structured problem responses

- **Pros:** Public errors have consistent identifiers. Diagnostic detail can remain in safe internal logs.
- **Cons / Risks:** New domain errors need explicit mapping and tests. Mapping changes can break clients.
- **Estimated Cost / Effort:** Medium

### Option B: Return raw exception messages from each controller

- **Pros:** Initial error handling needs less shared mapping.
- **Cons / Risks:** Messages and statuses can vary. Internal implementation details can become public.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Transport boundaries own HTTP representation. Stable identifiers let clients respond without depending on internal exception text.

## Consequences

- **Positive:** Domain, HTTP, unexpected, and fatal errors can use a common response structure.
- **Negative / Trade-offs:** A mapped status needs semantic review. Log correlation does not replace application failure-path tests.

## Implementation & Validation

- **Rollout Steps:** Define safe domain context. Add explicit HTTP mappings. Preserve existing public identifiers. Keep internal exception details in controlled diagnostics.
- **Verification:** Test each mapped status and identifier, unexpected failures, shutdown failures, and production detail suppression. Check the shared problem schema.
- **Review Date:** Review before changing a public error identifier, status, or diagnostic exposure policy.
