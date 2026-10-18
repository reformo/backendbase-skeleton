# ADR-0050: Contact Verification Before User Registration

- **Status:** Superseded by ADR-0051
- **Date:** 2026-10-15
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

ADR-0051 replaces the required email proof, client-selected contact identifiers, and registration lock order described below.
The remaining code, context, delivery, quota, and Admin rules remain applicable.

User registration requires proof of both email and mobile ownership before any identity records exist.
The evidence must be less than 12 hours old. Admin verification remains optional after enrollment and login.

## Decision Made

IdentityAndAccess owns `contact_verification`. Each record identifies a contact, purpose, realm, and secret context.
The client generates a random 256-bit context token. Storage uses a stable SHA-256 digest of that secret.
Six-digit codes use keyed hashes. Code hashes bind their record, generation, contact, purpose, and realm.

Registration acquires both evidence locks before reading the authorization time.
Both contacts must match, share the context, remain unused, and have verification timestamps within the 12-hour window.
The transaction creates the Account, User, and credentials and consumes both proofs atomically.
Rollback preserves both proofs. The registration deadline does not expire established login eligibility.

Optional Admin verification requires its existing Account and actor.
Before login, the request requires password possession. Authenticated requests require the ADMIN realm and the matching principal.
Verification preserves ownership, credential version, sessions, invitation, and separate approval requirements.
Account state uses the boolean `ownershipVerified`. User registration sets it after both contact proofs pass.
The trusted initial Admin bootstrap also sets `ownershipVerified: true`. Account enablement and actor approval remain separate checks.
No public Admin registration or mandatory login verification is introduced.

Each contact code generation has a distinct delivery identity. Delivery occurs after database commit.
Unknown outcomes are not automatically replayed. Explicit resend creates a new generation under the existing attempt and request limits.
Code validity is five minutes. Confirmed evidence supports idempotent confirmation recovery for 12 hours without renewal.

Proof-key rotation does not change context or quota identity.
Retain each required code key until pending codes and confirmation recovery windows expire.
The active/previous key configuration requires retaining the previous key before another rotation discards it.

## Consequences

The former post-registration email ownership endpoints and `identity_challenge` implementation are removed.
The initial identity migration supports fresh installations and creates `contact_verification` directly.
Existing installations require a separate adoption migration. This decision does not authorize applying migrations.
Account JSON containing the former `owned` key requires a separate data adoption step that preserves its boolean value as `ownershipVerified`.

MySQL and Redis do not share a transaction. Conservative request reservations can remain charged after interrupted operations.
Contact verification details, exact quotas, errors, and operational boundaries are documented in [contact verification](../platform/operations/contact-verification.md).

## Verification

Use notification doubles, isolated SQLite, frozen UTC time, and actual HTTP/container dispatch for local checks.
Run the full suite, PHPStan level 8, coding standards, complexity, API validation, and documentation checks.
Use prepared native MySQL targets for concurrent proof consumption and observed lock-wait evidence.
Report native checks and representative `EXPLAIN` plans as unverified when no prepared target is available.
