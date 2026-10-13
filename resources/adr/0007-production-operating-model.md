# ADR-0007: Production Operating Model

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Release scripts define artifact build, activation, rollback, and server hooks. They do not fully define process supervision, scheduling, capacity, or operational ownership.

The [deployment workflow](../../deployment/README.md) and [deployment guidance](../platform/18-deployment.md) require a production operating model.

- **Constraints:** Supervise HTTP and queue processes. Restart workers after activation. Define backup, recovery, scheduling, and alert ownership.

## Considered Options

### Option A: Managed servers with supervised application processes

- **Pros:** The existing deployment scripts and hooks can be used directly. Process ownership can remain explicit.
- **Cons / Risks:** The team must operate server configuration, supervisors, scheduling, and capacity.
- **Estimated Cost / Effort:** Medium

### Option B: Container platform with managed process scheduling

- **Pros:** The platform can standardize process restarts, resource limits, and rollout controls.
- **Cons / Risks:** Packaging, deployment integration, and cluster operation need additional work.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Not selected. Both options require an operating-environment decision.
- **Rationale:** Select the model from availability targets, team capability, recovery requirements, and measured operating cost. Deployment scripts alone do not resolve this choice.

## Consequences

- **Positive:** An accepted operating model will assign responsibility for each required process and recovery action.
- **Negative / Trade-offs:** Either option adds environment-specific configuration and continuing operational work.

## Implementation & Validation

- **Rollout Steps:** Define service targets and process inventory. Select the target model. Document supervision, scheduling, backup hooks, and worker restart procedures.
- **Verification:** Demonstrate process recovery, release activation, worker restart, backup restoration, and rollback in the selected environment.
- **Review Date:** Review before the first production deployment and when availability or capacity requirements change.
