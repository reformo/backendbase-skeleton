# ADR-0007: Production Operating Model

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Release scripts define artifact build, activation, rollback, and server hooks. They do not fully define process supervision, scheduling, capacity, or operational ownership.

The target operating model uses managed servers with supervised application processes. Linux systemd supervises application processes. Repository units define relay and cleanup timers and queue consumer instances. Host installation, secret-provider authentication, backup hooks, and activation remain deployment operations.

The [deployment workflow](../../deployment/README.md) and [deployment guidance](../platform/18-deployment.md) require a production operating model.

- **Constraints:** Deploy reviewed immutable release artifacts. Keep secrets outside archives. Supervise the HTTP runtime, outbox relay, and queue consumers. Restart workers after activation. Define backup, recovery, scheduling, and alert ownership.

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

- **Chosen Option:** Option A: Managed servers with supervised application processes.
- **Rationale:** The existing immutable release workflow and server hooks fit managed servers. Explicit process supervision provides startup and recovery controls. Scheduling and assigned operational owners cover maintenance, backup, monitoring, and capacity responsibilities.

## Consequences

- **Positive:** Releases use the existing activation and rollback flow. Each required process and recovery action has a defined operational owner. Supervisors start and recover the configured application processes.
- **Negative / Trade-offs:** The team remains responsible for server configuration, supervision, scheduling, security updates, capacity, and recovery. Each environment needs working server hooks and verified operating procedures.

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Define service targets and process inventory. Select the hosting provider, supervisor, and scheduler. Configure processes to use the active release. Implement backup and process-activation hooks. Assign operational owners. Document restart, scheduled maintenance, monitoring, backup restoration, and rollback procedures.
- **Verification:** Demonstrate process recovery after failure and server reboot in a prepared stage environment. Verify release activation reloads the HTTP runtime and restarts relay and queue workers from the active revision. Test scheduled maintenance, readiness, backup restoration, and application rollback.
- **Review Date:** Review before the first production deployment and when availability or capacity requirements change.
