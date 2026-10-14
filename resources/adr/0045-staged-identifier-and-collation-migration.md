# ADR-0045: Staged Identifier and Collation Migration

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Identifier conversion can break logical references, tokens, sessions, caches, jobs, and reports. Collation conversion can change uniqueness and rebuild indexes.

The [decision report](references/2026-10-13-identity-access-decisions.md#migration-measurement-and-verification) provides staged conversion. Its integer-ID assumption is conditional on target inventory.

- **Constraints:** Inventory before drafting conversion. Preserve data and reference identity. Require explicit database-target authority. Do not treat conceptual SQL as executable migration evidence.

## Considered Options

### Option A: Inventory-led staged conversion with mapping and recovery gates

- **Pros:** Backfill and reference coverage can be measured before cutover. Compatibility and recovery have explicit checkpoints.
- **Cons / Risks:** Temporary fields, identifier maps, and optional dual access increase transition work.
- **Estimated Cost / Effort:** High

### Option B: One-step replacement of identifiers and text collation

- **Pros:** The transition has fewer temporary schema elements.
- **Cons / Risks:** Unmapped references, sessions, and uniqueness conflicts can appear during cutover. Large index changes can increase outage and recovery cost.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report requires inventory, allocation, backfill, reference conversion, cutover, and later cleanup. Only phases needed by the actual target state apply.

## Consequences

- **Positive:** Integer-to-UUID maps provide complete conversion evidence. Existing UUID-native schemas can avoid unnecessary identifier conversion.
- **Negative / Trade-offs:** Old JWT and Redis sessions need a selected mapping or controlled-logout plan if they exist. Technical identifier exceptions and recovery limits remain target-specific.

## Implementation & Validation

- **Rollout Steps:** Inventory the target and stop Example writers. Validate canonical existing UUIDs and all references. Create `example_table_uuid`. Copy existing UUIDs with `UUID_TO_BIN(uuid, 0)`. Enforce the selected collation and active uniqueness during the copy. Atomically rename the original table to `example_table_legacy_20261014` and activate the copy. Retain the original table for reviewed forward recovery. New timestamps use UTC microseconds. Do not restart an interrupted migration without inspecting its completed phase.
- **Verification:** Require complete backfill and zero duplicate or orphan references. Rehearse recovery on prepared staging data. Verify schema differences, sessions, jobs, reports, index size, and collation behavior.
- **Review Date:** Review before each target cutover or change to legacy identity and text compatibility.
