# ADR-0047: Generated UUID Projection Mapping

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The readable uuid column is a virtual database projection. Doctrine must not write it or remove it through an unintended schema difference.

The [decision report](references/2026-10-13-identity-access-decisions.md#open-choices-and-verification-gaps) presents two mapping strategies without selecting one.

- **Constraints:** Preserve CHAR(36), ascii, ascii_bin, BIN_TO_UUID(id, 0), and VIRTUAL semantics. Keep the projection separate from ORM identity and domain identity.

## Considered Options

### Option A: Leave the projection unmapped with migration guards

- **Pros:** The ORM record needs no display-only property. Application identity mapping stays small.
- **Cons / Risks:** Schema tools can propose removing an unknown column. Every generated schema difference needs a reliable guard.
- **Estimated Cost / Effort:** Medium

### Option B: Use supported read-only Doctrine projection mapping

- **Pros:** Metadata can describe the database projection and potentially reduce schema drift.
- **Cons / Risks:** Generated-field behavior depends on actual ORM and DBAL support. Incorrect mapping can write or alter the column.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Not selected. Both strategies require schema-diff and persistence verification.
- **Rationale:** The report leaves this choice open. The installed ORM version alone does not establish safe generated-column behavior on the target database.

## Consequences

- **Positive:** A selected strategy will give the readable projection explicit preservation and write rules.
- **Negative / Trade-offs:** A conceptual attribute example does not prove support. Current package versions and actual schema behavior must be checked together.

## Implementation & Validation

- **Rollout Steps:** Compare both strategies on an isolated MySQL 8.4 schema. Verify insert, update, hydration, and schema differences. Select the smallest strategy that preserves the projection.
- **Verification:** Require no application writes to uuid and no unintended DROP or MODIFY. Preserve ascii_bin and virtual generation through repeated schema comparisons.
- **Review Date:** Review before adding the projection or changing Doctrine and DBAL versions.
