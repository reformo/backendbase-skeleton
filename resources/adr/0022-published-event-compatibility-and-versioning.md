# ADR-0022: Published Event Compatibility and Versioning

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Queued messages can outlive an application release. PHP refactoring must not change published event names or payload fields that existing consumers require.

[Producer contracts](../platform/05-integration-event-contracts.md) and [consumer registration](../platform/06-integration-event-consumers.md) resolve event names and versions explicitly.

- **Constraints:** Keep released names stable. Use typed payload fields. Preserve old carriers while old messages can still exist.

## Considered Options

### Option A: Explicit event versions with retained compatible carriers

- **Pros:** Producers and consumers can evolve with visible compatibility boundaries. Retained messages remain interpretable.
- **Cons / Risks:** Several versions can require parallel mapping and tests. Retirement depends on retention and deployment evidence.
- **Estimated Cost / Effort:** Medium

### Option B: One payload schema that changes with current code

- **Pros:** Only one carrier and mapping need maintenance.
- **Cons / Risks:** Older queued messages can fail after a release. Internal renaming can break consumers.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The event contract is independent of PHP implementation names. Incompatible released changes require a new version.

## Consequences

- **Positive:** Registry lookup and producer-to-consumer tests make compatibility explicit.
- **Negative / Trade-offs:** The registry does not provide automatic payload upgrading. Unknown versions are permanent contract failures.

## Implementation & Validation

- **Rollout Steps:** Add a new version for incompatible changes. Register its carrier and subscriber. Keep old contracts until retention and consumer evidence permit removal.
- **Verification:** Map actual producer arguments through the real registry and dispatcher. Test retained versions, invalid payloads, and unknown versions.
- **Review Date:** Review before a published schema changes or a supported event version is retired.
