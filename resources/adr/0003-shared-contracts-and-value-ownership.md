# ADR-0003: Shared Contracts and Value Ownership

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Several modules need common contracts and values. An unrestricted shared directory can become a source of business coupling and framework dependencies.

The [Shared boundary](../platform/02-architecture.md) permits stable cross-module contracts and values. Framework support belongs in Infrastructure.

- **Constraints:** Shared code must not import business contexts, Application, Infrastructure, or framework namespaces.

## Considered Options

### Option A: Restrict Shared to stable contracts and values

- **Pros:** Modules use a small common vocabulary. Shared types remain usable without infrastructure.
- **Cons / Risks:** Changes to shared types affect several modules. Promotion into Shared needs careful review.
- **Estimated Cost / Effort:** Medium

### Option B: Use Shared as a common implementation directory

- **Pros:** Teams can reuse concrete helpers and services quickly.
- **Cons / Risks:** Business and framework coupling spreads across modules. Ownership becomes unclear.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The common boundary must remain stable and independent. Shared implementations cannot become an indirect route between contexts.

## Consequences

- **Positive:** Cross-module contracts have a clear home. Framework replacement does not require changing Shared.
- **Negative / Trade-offs:** Some similar context-specific code remains separate until a stable common contract exists.

## Implementation & Validation

- **Rollout Steps:** Keep capability rules within their contexts. Place reusable framework support in Infrastructure. Review each new Shared dependency.
- **Verification:** Run the [Shared architecture checks](../../tests/Architecture). Inspect shared contract changes for effects on every caller.
- **Review Date:** Review when several contexts require a new common capability or a shared contract changes.
