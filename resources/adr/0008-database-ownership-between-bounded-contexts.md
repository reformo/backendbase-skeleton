# ADR-0008: Database Ownership between Bounded Contexts

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The platform enforces PHP context boundaries but currently supplies one Doctrine database connection. Namespace checks do not prevent cross-context SQL access.

[Doctrine composition](../../config/dependencies/doctrine.php) supplies the shared connection model. [Persistence guidance](../platform/09-persistence.md) defines context-owned ports and adapters. This record establishes exclusive context table ownership within the shared database. Table inventory and SQL ownership verification remain pending.

The [final decision report](references/2026-10-13-identity-access-decisions.md#repository-comparison) selects logical references without physical foreign keys. It does not select shared or separate context databases.

- **Constraints:** Assign each business table and its schema changes to one context. Keep direct SQL access and joins within that context's tables. Use published contracts across context boundaries. Preserve the selected no-foreign-key policy. Keep coupled business, subscriber, and outbox writes on one transaction connection.

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

- **Chosen Option:** Option A: Shared database with exclusive context table ownership.
- **Rationale:** The shared database preserves the existing composition and transaction connection. Each context owns its business tables, data access, and schema changes. Other contexts use published contracts or event-fed projections. Infrastructure owns shared technical messaging tables and accesses them through its own components.

## Consequences

- **Positive:** Table and migration ownership become explicit. Owning components can participate in one atomic transaction while contexts retain control of their data.
- **Negative / Trade-offs:** Shared credentials do not enforce context isolation at the database level. SQL and migration reviews must enforce ownership. Cross-context reporting can require event-fed projections or application coordination.

## Implementation & Validation

- **Rollout Steps:** Inventory business and technical table owners. Assign migration changes to those owners. Replace direct cross-context SQL access with published contracts or context-owned projections. Keep business, subscriber, and outbox writes coordinated through their owning components on the shared transaction connection.
- **Verification:** Review SQL, joins, and migrations against table ownership and the [logical-reference policy](0042-logical-references-without-physical-foreign-keys.md). Verify shared-connection rollback for coupled business, subscriber, and outbox writes. Check that cross-context operations use contracts or owned projections without direct access to another context's tables.
- **Review Date:** Review before adding a second business context or changing database isolation.
