# Contact Verification

User registration requires email and mobile input. Only mobile verification is required before creating identity records.
Email verification is optional before registration and available after login.
IdentityAndAccess owns `contact_verification`. Each row records one contact slot, purpose, realm, and verification context.
The Account remains the owner of the email and mobile used for login.

## User API flow

1. Generate 32 random bytes with a cryptographic random generator.
2. Encode the bytes as canonical unpadded base64url.
3. Keep the resulting 43-character `verificationContextToken` secret.
4. Request mobile verification through `POST /auth/contact-verifications`.
5. Supply `contactType: mobile`, `contactValue`, and `verificationContextToken`.
6. Confirm through `POST /auth/contact-verifications/{contact-info-id}/confirm`.
7. Supply the context token and six-digit `code`.
8. Optionally verify email with the same context token.
9. Register through `POST /auth/register`.
10. Supply email, mobile, password, and the context token.

Registration fields are `email`, `mobile`, `password`, and `verificationContextToken`.
The server rejects `emailContactInfoId` and `mobileContactInfoId` as unknown registration fields.
It finds contact slots through the context token, USER realm, registration purpose, and contact type.
The required mobile value must match its verification evidence exactly after normalization.
The server rejects required evidence from another context, purpose, or realm.
Email is lowercase ASCII. Mobile uses E.164 format without spaces or inferred country codes.

The required mobile verification timestamp must precede or equal server time and be less than 12 hours old.
Evidence exactly 12 hours old is expired. This deadline applies only to registration.
Normal login remains available after that interval.

Registration looks up and locks email first, then mobile, before reading the authorization time.
It consumes fresh matching mobile evidence and creates identity records in one transaction.
It also consumes matching, unused email evidence when that evidence meets the same freshness requirement.
Missing, unconfirmed, expired, future, consumed, or different email evidence does not block registration.
Rollback preserves all selected proofs. Concurrent registration permits only one successful consumption.
Account uniqueness constraints remain the final guard for duplicate email and mobile.

## User email verification after login

1. Log in through `POST /auth/login` with the Account email or mobile and password.
2. Generate a new context token.
3. Send `contactType: email` and `verificationContextToken` to `POST /identity/contact-verifications`.
4. Send the USER bearer token with each request.
5. Confirm through `POST /identity/contact-verifications/{contact-info-id}/confirm` with the context token and six-digit code.
6. Use `POST /identity/contact-verifications/{contact-info-id}/resend` with the context token when another code is required.

The server selects the email from the authenticated Account. It rejects a supplied destination and the mobile contact type.
The evidence purpose is `user-contact-verification`. Each record belongs to the authenticated Account and User actor.
Another User, an ADMIN token, or an anonymous registration route cannot confirm or resend this evidence.
Confirmation persists `verifiedAt`. The response has `registrationValidUntil: null` because this evidence does not authorize registration.
Verification preserves Account ownership, passwords, authorization versions, and sessions. It does not change the email value.
ExampleApi exposes the same flow with its required API key.

## Code and context state

Codes expire after five minutes. Each contact permits five failed confirmations across resends.
Failed confirmation commits its attempt count before returning rejection.
Requesting an existing context slot returns its current state without another notification.
Changing a contact value requires a new context.

Use `POST /auth/contact-verifications/{contact-info-id}/resend` with the context token for an explicit resend.
Resend replaces the code and invalidates the previous code. It preserves failed attempts.
Resend cannot renew verified evidence. Start a new context when verification must be repeated.

A repeated successful confirmation returns the original verification timestamp within its 12-hour window.
This permits recovery after a lost confirmation response without extending proof lifetime.
A repeated registration after consumption returns a conflict. Use normal login after a lost registration response.

Only SHA-256 context digests are stored. The context token has 256 bits of random input.
Six-digit code hashes use the keyed proof hasher and bind contact, generation, purpose, realm, and record identity.
Neither the plaintext code nor the raw context token is stored in `contact_verification`.
The contact record retains code deadlines, attempts, delivery state, verification time, and consumption evidence.
`ContactState` provides immutable typed access to its stored JSON snapshot. Reconstruction validates all declared field types.
Mapped columns remain authoritative for contact identity, owner references, verification time, and row revision.
The JSON snapshot retains its existing duplicate fields. Do not log or expose its complete serialization.

## Optional Admin verification

Admin enrollment continues to require invitation and separate approval. Public Admin registration remains absent.
A pending invited Admin can request verification with its identifier, password, contact type, and context token.
The anonymous Admin route derives the destination from the stored Account.
It requires an active Account with `state.ownershipVerified: true` and an active invited Admin actor.
Confirmation and resend require the context secret and a current Account and actor.

After login, use `/identity/contact-verifications` and its `/{contact-info-id}/confirm` and `/resend` paths.
These routes require an ADMIN bearer token. The contact record must match the authenticated Account and actor.
They verify only stored contacts. They do not change contact values.

Starting, failing, resending, or completing optional verification preserves ownership and `authVersion`.
USER and ADMIN sessions remain valid. Verification grants neither approval nor a login token.
ADMIN evidence cannot authorize USER registration.

## Delivery and request limits

Each contact and code generation has a separate stable delivery UUID.
The identity transaction commits before synchronous notification delivery through `ReliableNotificationDelivery`.
Email and SMS use the configured existing providers. Provider acceptance does not prove final delivery.

Inspect local SNS and SES verification messages with `composer message-viewer` at `http://127.0.0.1:8083`.
The viewer requires `BACKENDBASE_ENV=dev` in the process environment or `.env`.
Select SMS or Emails. Expand an email to view its headers and preview.
The PHP viewer reads MiniStack captures and refreshes every five seconds. It does not create or resend a verification request.
It shows the first SMS line, up to MiniStack's 80-character log limit. Some raw emails have no stored recipient.
See [local service limits](../19-external-services.md).

Local setup requires `BACKENDBASE_IDENTITY_CONTACT_EMAIL_SENDER` and a valid `BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY` with its key identifier.
The example environment supplies a sender and a public 32-byte proof key for local development.
Its default SES endpoint targets MiniStack. Replace the example keys and configure a real provider before deployment.

A provider exception leaves an unknown outcome. Process loss can leave pending delivery state.
Do not automatically replay either state. A repeated create request only returns the existing state.
An explicit resend creates a new generation after the cooldown and quota checks.
Completion from an older generation cannot replace the current delivery state.
A correct code can confirm ownership even when provider acceptance could not be recorded.

Redis performs atomic request reservations with expiring counters:

| Limit | Scope |
| --- | --- |
| 60-second send cooldown | Normalized contact across contexts and realms |
| Five sends per hour | Normalized contact across contexts and realms |
| Twenty sends per hour | Direct caller IP address |
| Ten failed or outstanding confirmations per hour | Normalized contact across contexts and realms |

A successful confirmation releases its outstanding failure reservation.
An interrupted or failed operation keeps that reservation. This conservatively limits retries.
Hourly counters expire one hour after their first reservation. Resend does not renew their window.
A reservation rejected by a later database race can remain charged. It never permits an extra send.
Use `REMOTE_ADDR` for the client address. Forwarded headers do not override this address.
Counter identifiers use stable SHA-256 digests. Proof-key rotation does not reset quotas or contact slots.
These digests are lookup identifiers, not encryption. Do not log them or notification payloads.
An unavailable limiter fails with 503 before the operation begins its verification mutation.
MySQL and Redis do not share a transaction.

## Errors and key rotation

Success uses the `contactInfo` response key. No code or context secret appears in the response.
Malformed requests return 400. Invalid proof or authority returns 401. Conflicts return 409.
Expired codes or evidence return 410. Request limits return 429 with `Retry-After`.
Dependency failures return 503. Errors retain the root Problem Details structure.

Code hashing retains the `contact-proof-key` and `previous-contact-proof-key` configuration names.
Keep an issued hashing key until every dependent code and confirmation recovery window expires.
Pending codes depend on their code deadline. Confirmed records depend on `verifiedAt + 12 hours`.
Do not discard a required previous key during a second rotation.
Context and quota identities remain independent of those rotating keys.

## Verification and migration boundaries

Focused tests cover request, confirmation, resend, quotas, delivery failure, key rotation, rollback, and HTTP dispatch.
Frozen-clock tests cover both time boundaries and time changes during evidence acquisition.
The full suite includes architecture, mapping, source/merged OpenAPI, and Bruno contract checks.
Native MySQL tests cover concurrent consumption and observed lock waits on prepared isolated targets.
Native Redis tests execute the cooldown, send-budget, and confirmation-reservation scripts on a prepared target.
SQLite tests do not prove MySQL locking or optimizer behavior.

The initial identity migration replaces `identity_challenge` with `contact_verification` for fresh installations.
Review generated SQL against production metadata. Existing installations require a separate adoption plan.
Do not apply migrations or send real notifications as part of local verification.
