# ADR-0008: Database Ownership between Bounded Contexts

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The platform enforces PHP context boundaries but currently supplies one Doctrine database connection. Namespace checks do not prevent cross-context SQL access.

[Doctrine composition](../../config/dependencies/doctrine.php) and [persistence guidance](../platform/09-persistence.md) leave physical data ownership policy open.

The [final decision report](references/2026-10-13-identity-access-decisions.md#repository-comparison) selects logical references without physical foreign keys. It does not select shared or separate context databases.

- **Constraints:** Define table ownership, migration ownership, permitted joins, and contract access. Preserve the selected no-foreign-key policy. Keep coupled business, subscriber, and outbox writes on one transaction connection.

## Considered Options

### Option A: Shared database with exclusive context table ownership

- **Pros:** Existing composition remains usable. Each context can own its schema changes without direct access by other contexts.
- **Cons / Risks:** Database permissions may not enforce the boundary. Cross-context queries need contract-based alternatives.
- **Estimated Cost / Effort:** Medium

### Option B: Separate database or schema and credentials per context

- **Pros:** Physical access controls strengthen data ownership. Contexts can evolve storage independently.
- **Cons / Risks:** Cross-context transactions and reporting need explicit coordination. Operations become more complex.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Not selected. Option A is a candidate for the current shared connection.
- **Rationale:** Confirm required isolation and cross-context reporting before adoption. A shared connection does not establish permission to join or update another context's tables.

## Consequences

- **Positive:** An accepted policy will make schema and data access ownership reviewable.
- **Negative / Trade-offs:** Strong ownership can require event-fed projections or application coordination. Separate connections cannot preserve the current shared-connection transaction without an explicit transaction design.

## Implementation & Validation

- **Rollout Steps:** Inventory table owners and cross-context reads. Define permitted access. Select physical isolation and migration ownership rules.
- **Verification:** Review SQL and migrations against table ownership and [logical-reference policy](0042-logical-references-without-physical-foreign-keys.md). Verify transaction atomicity and forbidden access under the selected connection model.
- **Review Date:** Review before adding a second business context or changing database isolation.
