# ADR-0048: Business-Key Normalization and Uniqueness

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The target Turkish collation affects key equality. Global Account email and actor login names are distinct concepts; the report's sample login index is conditional.

The [decision report](references/2026-10-13-identity-access-decisions.md#open-choices-and-verification-gaps) leaves exact normalization, uniqueness scope, and active-key rules open.

This record selects realm-scoped actor login names with explicit normalization. Login names contain 3 to 64 ASCII letters, digits, dots, underscores, or hyphens and start with an alphanumeric character. Domain normalization converts them to lowercase. Account emails use valid lowercase ASCII addresses. Active actor names can be reused after deletion. Conflicting restoration is rejected. Account email uniqueness is global among active Accounts.

- **Constraints:** Define normalization in Domain. Scope actor login lookup and uniqueness to the trusted USER or ADMIN realm. Use the same normalized value for lookup, persistence, and uniqueness checks. Keep Account identity global and Account email rules separate. Define deleted-row participation explicitly. Test actual key equality before migration.

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

- **Chosen Option:** Option B: Realm-scoped actor login names with explicit normalization.
- **Rationale:** USER and ADMIN manage actor login names independently while referencing global Accounts. Login lookup and uniqueness use the pair (realm, normalized login name). The same normalized name may exist in different realms. Domain-defined normalization and matching database constraints must enforce equivalent names within each realm.

## Consequences

- **Positive:** Actor login uniqueness has an explicit realm boundary. Lookup and database constraints follow the same normalization contract. Names in one realm do not reserve names in another.
- **Negative / Trade-offs:** Every actor login lookup and uniqueness check requires a trusted realm. I/İ/ı/i, accents, Unicode normalization, and trailing spaces can change equality. Non-ASCII login names and emails, leading or trailing whitespace, and invalid bounds are rejected. Closed Account emails and removed actor names can be reused under the active-key rules.

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Inventory required actor login keys. Define case, Unicode, whitespace, and comparison rules in Domain. Define deleted-row participation, reuse, and restoration behavior. Analyze existing duplicates within each realm. Apply the same normalization to lookup and persistence. Add realm-scoped uniqueness constraints under the approved lifecycle rules. Define Account email rules separately before changing them.
- **Verification:** Test normalized lookup and equivalent and distinct keys on the target collation. Reject conflicting normalized names within one realm. Permit the same normalized name across USER and ADMIN. Test Turkish I/İ/ı/i, accents, Unicode forms, trailing spaces, concurrent duplicates, approved reuse, and restoration conflicts. Verify lookup never searches another realm implicitly.
- **Review Date:** Review before creating key constraints or changing login and email normalization.
