# ADR-0011: Secret Storage and Rotation

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Deployments link an external environment file into each release. Secrets are excluded from release archives, but storage access and rotation procedures remain environment-specific.

The [deployment workflow](../../deployment/README.md) provides the current file model. [Configuration guidance](../platform/15-configuration.md) requires controlled runtime values.

- **Constraints:** Keep secrets out of source, archives, and logs. Define access ownership, rotation, revocation, and worker refresh.

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

- **Chosen Option:** Not selected. The existing file mechanism is a baseline, not a complete rotation policy.
- **Rationale:** Select storage and refresh behavior from environment access controls, credential lifetime, and recovery needs. Neither option removes the need for tested rotation.

## Consequences

- **Positive:** An accepted policy will identify who can read, replace, and revoke each secret.
- **Negative / Trade-offs:** Rotation can require coordinated provider changes and process restarts.

## Implementation & Validation

- **Rollout Steps:** Inventory secret owners and consumers. Select storage and injection. Define rotation overlap, revocation, rollback, and worker refresh.
- **Verification:** Test rotation and revocation in an isolated environment. Verify artifacts and diagnostics contain no secret values.
- **Review Date:** Review before production use and when a provider or credential lifecycle changes.
