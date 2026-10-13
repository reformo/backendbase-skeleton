# ADR-0048: Business-Key Normalization and Uniqueness

- **Status:** Proposed
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The target Turkish collation affects key equality. Global Account email and actor login names are distinct concepts; the report's sample login index is conditional.

The [decision report](references/2026-10-13-identity-access-decisions.md#open-choices-and-verification-gaps) leaves exact normalization, uniqueness scope, and active-key rules open.

- **Constraints:** Define normalization in Domain. Keep Account identity global. Test actual key equality before migration. Do not infer a universal rule from conceptual SQL.

## Considered Options

### Option A: Globally unique actor login names with explicit normalization

- **Pros:** One actor login lookup can be unambiguous across realms.
- **Cons / Risks:** Unrelated actor realms share one name space. Active-key reuse and restoration need agreed global rules.
- **Estimated Cost / Effort:** Medium

### Option B: Realm-scoped actor login names with explicit normalization

- **Pros:** USER and ADMIN can manage actor names independently while referencing global Accounts.
- **Cons / Risks:** Lookup and uniqueness require the realm. Account email rules remain a separate decision.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Not selected. Uniqueness scope must be defined for each required business key.
- **Rationale:** The report defines the text standard and domain ownership but asks whether business uniqueness is global. Login names may need no uniqueness if the domain does not require it.

## Consequences

- **Positive:** A selected policy will align domain normalization, database constraints, lookup, key reuse, and restoration.
- **Negative / Trade-offs:** I/İ/ı/i, accents, Unicode normalization, and trailing spaces can change equality. Soft-deleted rows and active replacement require explicit treatment.

## Implementation & Validation

- **Rollout Steps:** Inventory required keys. Define normalization, comparison exceptions, uniqueness scope, and deleted-row reuse. Analyze existing duplicates before choosing constraints.
- **Verification:** Test normalized lookup, equivalent and distinct keys, concurrent duplicates, approved reuse, and restoration conflicts on the target collation.
- **Review Date:** Review before creating key constraints or changing login and email normalization.
