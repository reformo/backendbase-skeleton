# ADR-0051: Optional Email Verification After User Login

- **Status:** Accepted
- **Date:** 2026-10-18
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.
- **Partially superseded by:** [ADR-0052](0052-configurable-registration-verification.md) for configurable registration proof requirements and separate User registration acceptance.
- **Supersedes:** The registration email requirement, client-selected evidence identifiers, and registration lock order in [ADR-0050](0050-contact-verification-before-user-registration.md).

## Context and Problem Statement

User registration needs an email address without requiring email ownership verification first.
Mobile ownership remains a registration requirement. Registration must select evidence from the submitted contact values and context secret.
Authenticated Users need a separate flow to verify their stored email.

## Decision Made

Registration requires `email`, `mobile`, `password`, and `verificationContextToken`.
It rejects the former `emailContactInfoId` and `mobileContactInfoId` request fields.
Contact normalization, active Account uniqueness, password rules, and optional names remain unchanged.

Registration finds the context slots through the existing unique context, realm, purpose, and contact-type index.
It acquires email and mobile locks in that order before reading the authorization time.
The mobile evidence must match the submitted mobile, remain unused, and be less than 12 hours old.
Matching fresh email evidence is consumed when available. Other email evidence does not block registration.
Identity creation, credentials, and selected proof consumption share one transaction.
The verified mobile satisfies `AccountState.ownershipVerified`. Email verification does not block password login or existing sessions.

UserApi and ExampleApi expose authenticated request, confirmation, and resend operations under `/identity/contact-verifications`.
They require the USER realm and select only the stored Account email. ExampleApi also requires its API key.
The `user-contact-verification` purpose separates these records from registration and Admin evidence.
Each record binds its Account and User actor. Confirmation persists the verification timestamp without changing Account or session state.
These proofs cannot authorize registration. Their response has no registration validity deadline.
Existing code expiry, failed-attempt limits, resend limits, delivery behavior, and context secrets remain applicable.
Admin invitation, approval, optional verification, and authentication behavior remain unchanged.

## Consequences

Registration clients must remove both contact identifier fields. They must keep the mobile verification context token.
Email input remains required and globally unique among active Accounts.
Account ownership and verified email are separate facts. Email evidence resides in `contact_verification`.
Existing state and columns support this flow without a schema change or data rewrite.
The contact-slot index and binary primary key cover the lookups. Native MySQL locking and optimizer checks need a prepared target.

## Verification

Use isolated SQLite, frozen UTC time, notification doubles, and production HTTP dispatch for local behavior checks.
Cover optional email evidence, required mobile proof, rejected legacy fields, post-login verification, resend, expiry, and Account isolation.
Retain rollback, deadline-after-lock, cross-realm, contract parity, and native concurrency tests.
Run PHPStan level 8, complexity, coding standards, API validation, and documentation checks.
