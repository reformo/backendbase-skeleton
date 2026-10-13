# ADR-0012: Immutable Releases and Schema-Compatible Rollback

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

A release must bind application code, dependencies, generated contracts, and migration policy to one reviewed revision. Recovery must account for schema compatibility.

The [deployment workflow](../../deployment/README.md) and [release policy](../../deployment/release.json) define immutable archives and recorded previous-release rollback.

- **Constraints:** Exclude environment secrets from archives. Verify artifact checksums. Do not assume application rollback reverses MySQL schema changes.

## Considered Options

### Option A: Immutable artifacts with atomic activation and explicit rollback policy

- **Pros:** One artifact identifies deployed content. Activation and previous-release recovery are controlled.
- **Cons / Risks:** Build evidence, backup hooks, and worker activation hooks need maintenance. Expanded schemas must support the previous application.
- **Estimated Cost / Effort:** Medium

### Option B: Update a server working tree and install dependencies in place

- **Pros:** Deployment needs less artifact infrastructure initially.
- **Cons / Risks:** Servers can produce different content. Partial updates and recovery are harder to identify.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Reviewed artifacts and manifests make deployment content and migration targets explicit. Atomic activation reduces partial application updates.

## Consequences

- **Positive:** Deployment and recovery use recorded releases with verified content.
- **Negative / Trade-offs:** Database recovery remains separate. A failed data-definition statement can require forward repair or restoration.

## Implementation & Validation

- **Rollout Steps:** Build the exact revision. Verify its manifest and checksum. Prepare recovery. Apply authorized migrations. Activate and restart workers.
- **Verification:** Run [deployment fixture checks](../../tests/Deployment/deployment-scripts.sh). Demonstrate readiness and recorded previous-release recovery in the target environment.
- **Review Date:** Review when deployment layout, migration compatibility, or recovery requirements change.
