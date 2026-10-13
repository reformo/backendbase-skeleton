# ADR-0018: Production Observability

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The platform provides UTC logs, trace identifiers, readiness, and outbox status. It has no configured metric exporter, distributed tracing, dashboard, or alerts.

The [observability guidance](../platform/16-errors-observability.md) and [logger composition](../../config/dependencies/logger.php) define the existing signals.

- **Constraints:** Protect credentials and personal data. Limit signal cardinality. Assign incident ownership and measurable service targets.

## Considered Options

### Option A: Central logs, operational metrics, and distributed tracing

- **Pros:** HTTP, queue, and provider behavior can be correlated. Alerts can measure latency, failures, and pending message age.
- **Cons / Risks:** Exporters, storage, retention, and alert tuning add cost and operational work.
- **Estimated Cost / Effort:** High

### Option B: Central logs and scheduled health and outbox checks

- **Pros:** Existing signals can support a smaller initial operating model.
- **Cons / Risks:** Latency trends and cross-process diagnosis remain limited. Detection can depend on scheduled checks.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Not selected. The current log and health signals do not define a complete production policy.
- **Rationale:** Select signals and retention from service targets, incident response needs, and operating cost. Define required alerts before selecting tools.

## Consequences

- **Positive:** An accepted policy will make failure detection and incident ownership measurable.
- **Negative / Trade-offs:** Signal storage and alert handling require continuing maintenance.

## Implementation & Validation

- **Rollout Steps:** Define service targets and incident owners. Select required signals. Specify safe fields, retention, exporters, dashboards, and alert thresholds.
- **Verification:** Simulate dependency failure, queue backlog, and provider timeout. Confirm useful correlation, alert delivery, and absence of sensitive payloads.
- **Review Date:** Review before production operation and after significant incidents or capacity changes.
