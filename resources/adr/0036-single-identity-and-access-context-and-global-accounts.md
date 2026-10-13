# ADR-0036: Single IdentityAndAccess Context and Global Accounts

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The target architecture contains User and System Admin actors linked to global Account identity. Authentication flows must preserve common identity rules and separate actor business lifecycles.

The [decision report](references/2026-10-13-identity-access-decisions.md#context-and-actor-scope) selects one IdentityAndAccess context. This is a target decision; the current repository has no implementation of that context.

- **Constraints:** Keep existing directories and framework conventions. Limit actor scope to User and System Admin. Keep domain rules outside controllers, SQL, Redis, and JWT adapters.

## Considered Options

### Option A: One identity context with separate actor aggregates

- **Pros:** Account, credentials, MFA, sessions, token issuance, and access policy have one owner. Actor business rules retain separate aggregate ownership.
- **Cons / Risks:** Realm policy and actor lifecycle need explicit coordination. Global identity does not make User and Admin interchangeable.
- **Estimated Cost / Effort:** Medium

### Option B: Separate identity contexts for User and System Admin

- **Pros:** Each identity context can own its login implementation independently.
- **Cons / Risks:** Account and credential rules can diverge. Separate login flows alone do not justify duplicated identity ownership.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The approved scope shares identity capabilities while keeping actor business behavior separate. Realm isolation belongs in authentication policy rather than duplicated identity contexts.

## Consequences

- **Positive:** Account identity is global. User and Admin aggregates retain their own identifiers, lifecycle rules, and scalar Account references.
- **Negative / Trade-offs:** Actor cardinality and business-key rules still need domain definitions. The report defines no tenant model or additional actor types.

## Implementation & Validation

- **Rollout Steps:** Inventory existing aggregate ownership. Add identity contracts and orchestration within the current module structure. Bind JWT, Redis, and notification capabilities through ports.
- **Verification:** Check domain purity, context composition, actor lifecycle boundaries, and identifiers. Verify both realms resolve through the same identity capability.
- **Review Date:** Review when actor scope, global Account ownership, or a tenant requirement changes.
