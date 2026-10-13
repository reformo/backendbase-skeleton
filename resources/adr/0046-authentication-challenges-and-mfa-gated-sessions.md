# ADR-0046: Authentication Challenges and MFA-Gated Sessions

- **Status:** Accepted
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
- **Negative / Trade-offs:** Admin invitation and approval, phishing-resistant MFA, and privileged recovery are report preferences. Their exact policy and mechanisms still need selection.

## Implementation & Validation

- **Rollout Steps:** Define complete challenge contracts and state transitions. Separate verification from actor approval. Gate session issuance. Select enrollment and recovery policies before activation.
- **Verification:** Test incomplete and expired challenges, denied actor approval, incomplete MFA, recovery paths, and absence of full session issuance before required factors succeed.
- **Review Date:** Review when authentication factors, enrollment, actor approval, or account recovery requirements change.
