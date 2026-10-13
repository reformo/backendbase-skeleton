# ADR-0017: Liveness and Dependency-Readiness Contract

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Operators need to distinguish a running process from an application that can use its required dependencies. These signals have different recovery meanings.

[Readiness composition](../../config/dependencies/readiness.php) checks MySQL, Redis, the selected queue, and object storage. [Health guidance](../platform/16-errors-observability.md) defines the HTTP signals.

- **Constraints:** Keep probes bounded. Report failure safely. Make liveness and readiness available without an API key.

## Considered Options

### Option A: Separate process liveness and aggregate dependency readiness

- **Pros:** Probes distinguish process failure from dependency failure. Readiness has one explicit dependency set.
- **Cons / Risks:** One unavailable dependency can make the whole API unready, including operations that do not use it.
- **Estimated Cost / Effort:** Medium

### Option B: One HTTP success probe

- **Pros:** The probe is simple and has little dependency cost.
- **Cons / Risks:** A successful response does not show whether application operations can reach required services.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Separate signals support appropriate restart and traffic decisions. All configured readiness checks must pass for a ready result.

## Consequences

- **Positive:** GET /_status reports liveness. GET /_status/ready returns status 503 when a required dependency fails.
- **Negative / Trade-offs:** Probe scope is conservative. Operators must avoid restart loops caused only by dependency failures.

## Implementation & Validation

- **Rollout Steps:** Keep liveness process-only. Register required readiness checks. Configure infrastructure timeouts and target probe behavior.
- **Verification:** Test each dependency success and failure. Verify public probe access, response status, safe diagnostics, and temporary connection cleanup.
- **Review Date:** Review when required dependencies or traffic-management behavior change.
