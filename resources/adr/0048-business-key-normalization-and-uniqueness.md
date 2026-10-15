# ADR-0048: Business-Key Normalization and Uniqueness

- **Status:** Accepted
- **Date:** 2026-08-31
- **Updated:** 2026-10-15
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

User and System Admin actors authenticate with the shared Account email or mobile and password. Actors do not have usernames.
Email and mobile are required at registration. The database is empty, so the initial identity migration defines the new schema directly.

The [decision report](references/2026-10-13-identity-access-decisions.md#open-choices-and-verification-gaps) requires explicit normalization, uniqueness, and active-key rules.

- **Constraints:** Define contact validation in Domain. Use matching lookup, persistence, and uniqueness values. Select actors through the trusted USER or ADMIN realm.

## Considered Options

### Option A: Required Account email and mobile identifiers

- **Pros:** Both actor types share one contact identity and password. Active contact uniqueness makes each lookup unambiguous.
- **Cons / Risks:** Contact reuse follows Account closure. Mobile format validation does not establish mobile ownership.
- **Estimated Cost / Effort:** Medium

### Option B: Separate actor usernames

- **Pros:** Each realm can assign a separate actor name.
- **Cons / Risks:** Usernames introduce an identifier that the product does not use.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Option A. This replaces the earlier realm-scoped actor username decision.
- **Rationale:** Required email and mobile belong to the global Account. User and Admin refer to that Account through scalar identifiers.

Email uses valid lowercase ASCII addresses with at most 254 characters.
Mobile uses E.164 format: `+` followed by 2 to 15 ASCII digits, with a nonzero first digit.
Reject whitespace, separators, extensions, unsupported Unicode, and invalid bounds. Do not infer country codes or silently repair input.

Registration accepts email, mobile, password, both verified contact IDs, and their shared context token. Login accepts `identifier` and `password`.
Resolve the active Account by email or mobile, then resolve its actor in the trusted realm.
Preserve ownership, approval, Account lifecycle, actor lifecycle, and password checks.
Both email and mobile require verification before User registration under [ADR-0050](0050-contact-verification-before-user-registration.md). Mobile codes use the existing SMS providers.

## Consequences

- **Positive:** Both actor types share required contacts. Generated active-email and active-mobile columns enforce global uniqueness atomically.
- **Negative / Trade-offs:** Removing an actor does not release Account contacts. Closing an Account releases both active keys while retaining stored contact values.

Current identity and actor history responses expose `email` and `mobile`. Historical actor reads join the retained Account through its primary key.
JWT claims retain their existing identity fields and contain no contact values.
Lifetime uniqueness still permits at most one User and one Admin per Account, including removed actors.

## Implementation & Validation

See the [implementation register](implementation-status.md) for the wider identity implementation and dated verification evidence.

- **Rollout Steps:** Update required Account contacts, both identifier lookup paths, actor models, API contracts, operator commands, and initial migration metadata together.
- **Verification:** Test both identifiers in both realms, email normalization, required mobile input, invalid formats, duplicate rollback, actor restrictions, and Account closure reuse.
- **Database Verification:** Review generated MySQL schema and index metadata. Run native uniqueness checks and representative `EXPLAIN` plans on an identified, prepared MySQL target.
- **Compatibility:** Existing installations require a separate migration and adoption plan. Do not apply the revised initial migration to an existing schema.
- **Review Date:** Review when contact ownership, contact updates, or Account reuse rules change.
