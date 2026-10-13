# ADR-0015: MySQL Migration Lifecycle and Baseline Adoption

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Schema changes must preserve application behavior and have an explicit recovery path. MySQL data-definition statements can commit independently of migration transactions.

The [schema-change rules](../platform/10-schema-changes.md) separate migration drafts, target execution, and adoption of rewritten migration history.

The [final decision report](references/2026-10-13-identity-access-decisions.md#migration-measurement-and-verification) adds staged identifier and collation conversion. Its integer-ID assumption must be verified against the actual target schema.

- **Constraints:** Apply only requested schema changes with explicit target authority. Compare schema and migration history before adoption. Inventory identifier, reference, session, and collation compatibility before conversion.

## Considered Options

### Option A: Reviewed non-transactional migrations with explicit adoption plans

- **Pros:** Migration SQL and recovery effects remain visible. Unexpected tables reveal schema drift.
- **Cons / Risks:** Failure can leave partial schema changes. Existing databases need separate history reconciliation after consolidation.
- **Estimated Cost / Effort:** Medium

### Option B: Automatic schema synchronization and implicit baseline marking

- **Pros:** New installations and initial schema changes need less manual review.
- **Cons / Risks:** Unexpected schema or history can be accepted silently. Recovery and data preservation become uncertain.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Reviewed SQL and explicit target authority reduce unintended database changes. Consolidated creation migrations apply directly only to compatible new databases.

## Consequences

- **Positive:** Deployment records identify the migration target and recovery evidence.
- **Negative / Trade-offs:** Application rollback cannot guarantee schema rollback. Existing database adoption and conversion require reviewed plans, duplicate analysis, and recovery evidence.

## Implementation & Validation

- **Rollout Steps:** Complete repository behavior and target inventory. Follow [staged conversion](0045-staged-identifier-and-collation-migration.md) where needed. Review SQL and recovery before authorized execution.
- **Verification:** Review dry-run SQL on the identified target. Verify identifier maps, logical references, business-key equality, and generated-column preservation. Rehearse [deployment recovery](../../deployment/README.md) before cutover.
- **Review Date:** Review when migration history is rewritten or a schema change affects backward compatibility.
