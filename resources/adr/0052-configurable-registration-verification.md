# ADR-0052: Configurable Registration Verification

- **Status:** Accepted
- **Date:** 2026-10-18
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.
- **Supersedes:** The fixed registration proof requirement and User ownership session gate in [ADR-0051](0051-optional-email-verification-after-user-login.md).

## Context and Problem Statement

Projects require mobile proof, email proof, both proofs, or neither proof before User registration.
Registration acceptance must remain separate from contact ownership evidence.
A policy change must not change existing Account or session authorization.

## Decision Made

`identity.registration.require-mobile-verification` defaults to `true`.
`identity.registration.require-email-verification` defaults to `false`.
Both settings use validated booleans. Environment values accept exactly `true` or `false`.
Missing settings retain defaults. Invalid values fail explicitly when registration dependencies resolve.
A context-owned `RegistrationPolicy` receives validated values through the production container.
UserApi and ExampleApi share the policy. Clients cannot select requirements in a registration request.

Email, mobile, and password remain required. Active contact uniqueness remains unchanged.
The context token is required when either proof is required. Otherwise it may be omitted or null.
Every supplied token must satisfy the existing format. Legacy contact identifier fields remain rejected.
The client verifies each required contact before registration. No policy discovery endpoint is added.
Clients must use the policy configured for their deployment.

When a context exists, registration locks email, then mobile, before reading authorization time.
Required proofs must match the values, context, realm, and purpose. Each must be unused and less than 12 hours old.
Registration also consumes usable optional proofs. Unusable optional evidence does not satisfy ownership or block registration.
Identity creation, credentials, acceptance, and selected proof consumption share one transaction.
The existing contact-slot index covers the unchanged lookup queries. No index or schema migration is required.

New Account state contains `registrationAccepted` separately from `ownershipVerified`.
Registration sets acceptance after all configured requirements pass.
It sets ownership only when it consumes proof. Registration without proof keeps ownership false.
The ownership flag retains its registration or trusted-bootstrap attestation meaning.
Later contact verification records remain separate and preserve Account flags and sessions, as specified in ADR-0051.
User login and sessions require stored acceptance. They never consult current registration settings.
Admin login, contact authority, invitation, and approval retain verified ownership and their existing actor checks.
Registration acceptance cannot authorize an Admin session or enrollment.

## Compatibility and Rollout

Legacy Account JSON without `registrationAccepted` derives acceptance from its required, validated `ownershipVerified` boolean.
An explicit acceptance field must be a boolean. Other missing, invalid, or unknown state fields remain rejected.
Existing verified and unverified Accounts retain their previous authorization results. Reads do not rewrite Account records.
Normal later Account writes serialize the new state shape. No bulk data rewrite runs.
Token claims, authentication versions, allowlist records, and session expiry remain unchanged.

Older application versions reject the added JSON field. Stop mixed-version access before new writes.
Clear input-mapping caches during deployment. Restart long-running processes after configuration changes.
Keep the two settings consistent across API instances. New registrations use the policy in effect for their request.
Rollback to older code requires a separate data compatibility plan for newly written state.
Local implementation does not deploy code, apply migrations, or write to a shared database.

## Consequences

The default registration flow remains mobile verification followed by registration and password login.
No-proof registration can create a usable User without falsely recording ownership verification.
Both contact values remain required and reserve unique active values even when unverified.
Contact correction and disputes remain separate concerns. Post-login User verification remains email-only.
A stricter deployment policy applies only to new registrations. Existing-account reverification is a separate feature.

## Verification

Test all policy and proof combinations through UserApi and ExampleApi with isolated SQLite and notification doubles.
Test required and optional evidence, expiry boundaries, context omission, invalid settings, rollback, and deterministic lock-time checks.
Test legacy true and false ownership state, existing sessions, stricter policy changes, and Admin isolation.
Test post-login email verification for an Account created without proof.
Run PHPStan level 8, complexity, coding standards, architecture and mapping tests, API validation, and documentation checks.
Native MySQL locking and optimizer checks require a separately identified, prepared target.
