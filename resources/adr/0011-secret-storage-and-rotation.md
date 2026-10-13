# ADR-0011: Secret Storage and Rotation

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Deployments link an external environment file into each release. Secrets are excluded from release archives, but storage access and rotation procedures remain environment-specific.

The target policy uses a managed secret store as the authoritative source. Controlled injection supplies runtime values. Provider selection, injection integration, and rotation verification remain pending.

The [deployment workflow](../../deployment/README.md) provides the current file model. [Configuration guidance](../platform/15-configuration.md) requires controlled runtime values.

- **Constraints:** Keep secrets out of source, archives, and logs. Separate environment credentials. Limit access to approved deployment and runtime identities. Define access ownership, rotation, revocation, and process refresh.

## Considered Options

### Option A: Managed secret store with controlled runtime injection

- **Pros:** Access and rotation can be recorded centrally. Environments can use separate credentials.
- **Cons / Risks:** Injection and recovery depend on an additional service. Cached process values still require a refresh strategy.
- **Estimated Cost / Effort:** High

### Option B: Restricted environment files with explicit rotation procedures

- **Pros:** The existing deployment model remains usable. Runtime reads need no secret-store service.
- **Cons / Risks:** File access, credential overlap, revocation, and audit procedures need manual controls.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Option A: Managed secret store with controlled runtime injection.
- **Rationale:** A managed store centralizes secret ownership, access records, and rotation controls. Controlled injection supplies validated values to application and worker processes while keeping credentials outside release artifacts.

## Consequences

- **Positive:** Each secret has an owner and controlled read, replacement, and revocation access. Separate credentials limit access across environments.
- **Negative / Trade-offs:** Injection and recovery depend on the managed store. Rotation can require coordinated credential changes, configuration-cache refresh, and process restarts.

## Implementation & Validation

- **Rollout Steps:** Inventory secret owners and consumers. Select a managed provider. Configure restricted deployment and runtime access. Inject required values outside release archives. Define rotation overlap, revocation, rollback, cache refresh, and process restart procedures.
- **Verification:** Test injection permissions, rotation overlap, revocation, cache refresh, and application and worker refresh in an isolated environment. Test denied access and store outages. Verify artifacts and diagnostics contain no secret values.
- **Review Date:** Review before production use and when a provider or credential lifecycle changes.
