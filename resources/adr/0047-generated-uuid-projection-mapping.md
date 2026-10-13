# ADR-0047: Generated UUID Projection Mapping

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The readable uuid column is a virtual database projection. Doctrine must not write it or remove it through an unintended schema difference.

The [decision report](references/2026-10-13-identity-access-decisions.md#open-choices-and-verification-gaps) presents two mapping strategies without selecting one. This record selects supported read-only Doctrine mapping. Implementation and database-backed verification remain pending.

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

- **Chosen Option:** Option B: Use supported read-only Doctrine projection mapping.
- **Rationale:** Explicit metadata makes the projection's read-only behavior visible to Doctrine and gives schema comparisons a defined column contract. The database generates the readable value from id. ORM identity and domain identity continue to use id.

## Consequences

- **Positive:** Doctrine can hydrate the readable projection while application inserts and updates exclude it. Metadata describes the column that schema tools must preserve.
- **Negative / Trade-offs:** Generated-column handling depends on supported ORM and DBAL behavior. Package upgrades require renewed persistence and schema-diff verification.

## Implementation & Validation

- **Rollout Steps:** Map uuid as a database-generated, read-only projection. Exclude it from inserts and updates. Preserve the migration-owned column definition. Verify insert, update, hydration, and repeated schema comparisons on an isolated MySQL 8.4 schema.
- **Verification:** Require no application writes to uuid and no unintended DROP or MODIFY. Check hydrated values against BIN_TO_UUID(id, 0). Preserve CHAR(36), ascii, ascii_bin, and VIRTUAL semantics through repeated schema comparisons.
- **Review Date:** Review before adding the projection or changing Doctrine and DBAL versions.
