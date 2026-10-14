# ADR-0041: MySQL 8.4 Character Set and Turkish Collation

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Business text comparison and uniqueness need an explicit database standard. Identifier representation must remain independent of linguistic comparison.

The [decision report](references/2026-10-13-identity-access-decisions.md#database-text-standard) selects MySQL 8.4, InnoDB, utf8mb4, and utf8mb4_tr_0900_ai_ci. New identity and recovery tables declare these defaults. Connections require utf8mb4. Identity key fields use ASCII binary overrides. Existing deployment schemas still require inventory before authorized rollout.

- **Constraints:** Set database and business-table defaults explicitly. Validate connection charset separately. Keep domain normalization authoritative for technical and business keys.

## Considered Options

### Option A: utf8mb4 with utf8mb4_tr_0900_ai_ci business-text defaults

- **Pros:** Turkish ordering and comparison follow one declared standard. New table definitions are consistent.
- **Cons / Risks:** Changed comparison rules can introduce uniqueness conflicts. Existing table and index conversion can require reconstruction and locks.
- **Estimated Cost / Effort:** Medium

### Option B: Keep inherited or legacy text collation defaults

- **Pros:** Existing schemas need less conversion work.
- **Cons / Risks:** Comparisons can differ across environments and tables. The final report replaces the older Turkish collation baseline.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report explicitly selects the Turkish UCA 9.0 collation as the target business-text default, with field-specific exceptions where domain rules require them.

## Consequences

- **Positive:** DBAL uses charset utf8mb4. Binary IDs have no text collation. The virtual UUID display field overrides the default with ascii_bin.
- **Negative / Trade-offs:** Case, accent, Unicode, and NO PAD trailing-space behavior affect uniqueness. Existing data must pass duplicate analysis before conversion.

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Use explicit MySQL server defaults for fresh Docker database initialization. Inventory existing database and column collations. Review duplicate and index impact before a separately authorized conversion. Follow [database collation procedures](../platform/operations/database-collation.md).
- **Verification:** Verify server support, connection charset, and actual table definitions on MySQL 8.4. Test I/İ/ı/i, accents, normalization, trailing spaces, and generated-field overrides.
- **Review Date:** Review when text equality, supported locales, database versions, or business-key rules change.
