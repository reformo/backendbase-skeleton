# ADR-0046: Authentication Challenges and MFA-Gated Sessions

- **Status:** Superseded by ADR-0049
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Contact ownership verification, login multi-factor authentication (MFA), and actor approval establish different facts. Their workflows must not be treated as interchangeable.

The [decision report](references/2026-10-13-identity-access-decisions.md#realm-authentication-and-sessions) assigns challenge coordination to IdentityAndAccess and business acceptance to actor aggregates.

- **Constraints:** Validate input at inbound boundaries. Keep challenge rules outside controllers. Do not issue a fully authorized session before required MFA completes.

## Considered Options

### Option A: Separate challenge workflows with explicit session gates

- **Pros:** Each verification proves its intended fact. Actor approval and authentication state retain clear ownership.
- **Cons / Risks:** Challenge expiry, failure limits, recovery, and realm-specific enrollment need explicit policies.
- **Estimated Cost / Effort:** High

### Option B: One verification flag for contact ownership, MFA, and actor approval

- **Pros:** Enrollment and login initially require fewer states.
- **Cons / Risks:** One proof can incorrectly authorize another action. Recovery and privileged enrollment boundaries become unclear.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The report explicitly separates ownership verification, MFA, and permission approval. Session issuance must wait for the authentication factors required by policy.

## Consequences

- **Positive:** Application orchestration uses challenge, token, and notification ports. User and Admin aggregates retain business lifecycle decisions.
- **Negative / Trade-offs:** Admin invitation and approval are required. Both USER and ADMIN use password and TOTP MFA. Contact ownership is separate. Challenges expire after five minutes and permit five attempts. Account-wide MFA failures block further attempts for five minutes after five failures. TOTP does not provide phishing resistance. Local root operator commands govern first-Admin setup and lost-factor recovery with separate evidence.

## Implementation & Validation

See the [implementation register](implementation-status.md) for current code, verification, and deployment selections.

- **Rollout Steps:** Use password, separate contact ownership proofs, and TOTP for both realms. Use local root operator commands for the first Admin invitation and approval. Record separate invitation and approval evidence. Lost-factor recovery records separate request and approval evidence, increments the credential version, clears factors, and requires contact ownership verification and new TOTP enrollment. Never issue sessions through operator commands.
- **Verification:** Test incomplete and expired challenges, denied actor approval, incomplete MFA, recovery paths, and absence of full session issuance before required factors succeed.
- **Review Date:** Review when authentication factors, enrollment, actor approval, or account recovery requirements change.
