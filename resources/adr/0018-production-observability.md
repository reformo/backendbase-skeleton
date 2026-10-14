# ADR-0018: Production Observability

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The platform provides structured logs, trace identifiers, readiness, and outbox status. It has no configured metrics exporter, distributed tracing, dashboards, or alerts.

The [final decision report](references/2026-10-13-identity-access-decisions.md#migration-measurement-and-verification) selects diagnostic logs and operational metrics as target requirements. It does not select observability products or require distributed tracing.

- **Constraints:** Exclude raw JWTs, private keys, credentials, and sensitive payloads. Keep signal cardinality controlled. Separate approved signal requirements from unresolved tools and alert policy.

## Considered Options

### Option A: Structured logs and operational metrics

- **Pros:** Latency, lock contention, authentication failures, and migration integrity can be measured. Existing health and outbox signals remain useful.
- **Cons / Risks:** Metric collection and operating procedures need implementation. Signal storage, thresholds, and response ownership still need selection.
- **Estimated Cost / Effort:** Medium

### Option B: Logs with health and outbox checks only

- **Pros:** The current signal set supports basic diagnosis with less new infrastructure.
- **Cons / Risks:** It does not provide the required latency, index, locking, revocation, and migration measurements.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report requires measured acceptance and recovery decisions. Logs and probes alone cannot supply the declared performance and integrity evidence.

## Consequences

- **Positive:** Required signals cover p95/p99 latency, index and buffer use, lock waits, deadlocks, reconciliation, schema drift, revocation delay, and security rejection rates.
- **Negative / Trade-offs:** This is an accepted minimum signal policy. Exporters, storage, retention, alert thresholds, incident ownership, and tracing adoption remain open. HTTP measurements use route patterns, method, status, and elapsed milliseconds. MySQL diagnostics expose aggregate lock, buffer, charset, collation, and size metrics. Exception diagnostics exclude messages and call arguments.

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Define safe signal fields and representative workloads. Add required measurements. Select collection, retention, and alert policies separately before production use.
- **Verification:** Measure real MySQL and Redis behavior on prepared targets. Simulate lock contention, revocation delay, and Redis failure. Confirm useful diagnostics contain no sensitive credentials or payloads.
- **Review Date:** Review when operational targets, diagnostic data policy, tooling, or measured failure behavior change.
