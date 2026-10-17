# ADR-0049: Password Authentication Without TOTP

- **Status:** Accepted
- **Date:** 2026-10-15
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.
- **Supersedes:** [ADR-0046](0046-authentication-challenges-and-mfa-gated-sessions.md).

## Context and Problem Statement

TOTP development is cancelled for both USER and ADMIN realms.
Ownership verification and Admin approval remain separate requirements.
The authentication contract must issue sessions without an enrollment or second login step.

- **Constraints:** Preserve realm isolation, password verification, current identity checks, Redis allowlists, and session revocation.

## Considered Options

### Option A: Remove TOTP and issue sessions after password verification

- **Pros:** One login request completes authentication. Factor secrets and recovery operations require no maintenance.
- **Cons / Risks:** Password possession is the sole login factor. Verified contact ownership remains an Account eligibility check.
- **Estimated Cost / Effort:** Medium

### Option B: Keep inactive TOTP code behind configuration

- **Pros:** Existing enrollment code remains available for later activation.
- **Cons / Risks:** Unused endpoints, secret storage, and recovery behavior remain part of the application.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Remove the cancelled capability and retain the required password, ownership, and actor gates.

`POST /auth/login` returns `session` with `accessToken`, `tokenType`, and `expiresAt`.
Remove both TOTP enrollment endpoints and `/auth/login/complete` from each identity API.
Remove TOTP commands, factor encryption, replay steps, failure counters, recovery, and rewrapping.
Retain local first-Admin invitation and approval with separate evidence.

## Consequences

- **Positive:** Login no longer creates a challenge. Contact verification retains expiry, attempt limits, resend invalidation, and proof hashing under [ADR-0050](0050-contact-verification-before-user-registration.md).
- **Negative / Trade-offs:** Clients must read the session from the password login response. Previous enrollment and completion calls return 404.

Accounts store their Argon2id password hash in required `account.password_hash VARCHAR(255)`.
The credential store reads and writes a string. The consolidated installation migration creates this column directly.
Existing databases with credential JSON require a separate reviewed hash extraction and schema adoption plan.
The former login and enrollment challenges cannot verify contact ownership. ADR-0050 replaces the email-only ownership schema for fresh installations. No automatic migration application or database cleanup runs.
Ownership proof hashing retains the `contactProofKey` configuration names and active/previous key support.
First-Admin invitation and approval evidence remains in Admin state.
The later `Version20261017173407` migration removes separate operator history under the [operator procedure](../platform/operations/identity-operator.md#operator-history-removal).

## Implementation & Validation

- **Rollout Steps:** Update API clients for the single login response. Deploy code and generated contracts together.
- **Verification:** Test password login, scalar hash persistence and replacement, closed-Account credential rejection, denied ownership and identity states, Admin approval, realm isolation, removed routes, and session revocation.
- **Review Date:** Review when authentication or recovery requirements change.
