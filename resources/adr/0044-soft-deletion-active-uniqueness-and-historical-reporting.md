# ADR-0044: Soft Deletion, Active Uniqueness, and Historical Reporting

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Operational queries need active records. Historical reports need deleted records and stored business dates without reconstructing past field values.

The [decision report](references/2026-10-13-identity-access-decisions.md#references-transactions-and-lifecycle) separates visibility, lifecycle, active-key uniqueness, and reporting.

- **Constraints:** Keep deleted_at separate from domain lifecycle state. Use real creation and business dates. Apply key constraints only where the domain requires them.

## Considered Options

### Option A: Soft deletion with active views and stored-value historical reports

- **Pros:** Removed rows remain available for reporting. Conditional active-key uniqueness can permit approved reuse.
- **Cons / Risks:** Each query must declare its visibility policy. Restoring a row can conflict with a replacement active key.
- **Estimated Cost / Effort:** Medium

### Option B: Temporal snapshots or event sourcing for historical reconstruction

- **Pros:** A complete implementation can reconstruct previous field values.
- **Cons / Risks:** It adds state history and recovery complexity that the stated reporting requirement does not need.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report requires deleted rows and their stored values, not past-state reconstruction. Soft deletion does not replace business lifecycle validation.

## Consequences

- **Positive:** Normal reads exclude deleted rows. Historical reads include them. Active-key generated columns can support atomic uniqueness where a business rule requires it.
- **Negative / Trade-offs:** Account email and mobile each have global active uniqueness under [ADR-0048](0048-business-key-normalization-and-uniqueness.md). Account closure releases these keys; actor removal does not. Historical queries include removed actors and join their retained Account for email and mobile. Actor restoration still requires an active Account.

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Declare operational and historical read ports. Apply approved active-key constraints. Implement restoration through lifecycle and key-conflict validation.
- **Verification:** Test active filtering, deleted-row reporting, permitted key reuse, restoration conflicts, and real-date ordering. Verify historical reads do not require write locks.
- **Review Date:** Review when deletion visibility, key reuse, restoration, or past-state reporting requirements change.
