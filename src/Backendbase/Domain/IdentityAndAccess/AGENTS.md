# IdentityAndAccess Instructions

Apply the [root rules](../../../../AGENTS.md) and relevant [platform guidance](../../../../resources/platform/README.md).
This context owns global Accounts, User and SystemAdmin actors, contact evidence, credentials, tokens, and sessions.
Its tables are `account`, `user`, `admin`, and `contact_verification`. Do not add bounded-context prefixes.

## Context rules

- Keep Account identity separate from actor identity. One Account can own at most one actor of each type, including removed records.
- Keep USER and ADMIN authentication realms separate. The JWT `sub` identifies the actor; `accountId` identifies the Account.
- Keep contact ownership, password authentication, and Admin approval as separate checks.
- Persist User registration acceptance as `state.registrationAccepted`. Keep it separate from `state.ownershipVerified`, Account enablement, and Admin approval.
- User login and sessions require stored registration acceptance. Admin login, enrollment, and sessions retain verified ownership. Never reapply registration configuration to existing identities.
- Read legacy Account state without `registrationAccepted` by deriving acceptance from its validated `ownershipVerified` value. Do not rewrite records during reads.
- Apply the [default JSON column policy](../../../../resources/platform/09-persistence.md#json-column-policy) to identity state without a mapping exception.
- Represent identity JSON state with immutable `AccountState`, `ActorState`, and `ContactState` classes under `Domain/Primitives`.
- Keep aggregate state in validated immutable snapshots. Keep eligibility, contact membership, and approval decisions on aggregates.
- Keep Doctrine state fields as arrays. Use typed `state()` reads and `replaceState()` writes with explicit `fromArray()` and `toArray()` conversion.
- Reject missing, mistyped, and unknown state fields. Keep storage serialization out of public responses and diagnostic logs.
- Require email and mobile input before User registration. Apply independent mobile and email proof requirements from the deployment policy. Default to required mobile and optional email.
- Require a context token when either proof is required. Permit omission or null only when neither is required. Validate every supplied token. Find evidence by submitted values and context.
- When a context is supplied, lock email and mobile slots in that order before checking time. Consume required and matching fresh optional proofs atomically with identity creation.
- Accept registration after all configured requirements pass. Mark ownership verified only when registration consumes evidence. Keep later contact verification records separate.
- Permit authenticated Users to verify their stored Account email after login. Bind evidence to the USER realm, Account, and actor. Preserve ownership and sessions.
- Password login returns a session after the required gates. TOTP enrollment and second-step login are absent under ADR-0049.
- Store the Argon2id password hash directly in `account.password_hash`. Keep the credential port scalar and preserve Account locks and state checks.
- Require both token and session allowlist records and current database identity state for authentication.
- Use independent realm access-token, idle, and maximum durations. Preserve each session policy and fixed maximum during renewal.
- Rotate refresh credentials atomically. Keep only hashes in Redis. Reuse for a new operation revokes the session.
- Authenticate refresh credentials before replay. Reject obsolete replay results before sending an older cookie.
- Require exact configured Origin for cookie login and renewal. Keep browser refresh secrets in Secure HttpOnly Strict cookies.
- Extend idle only after successful database eligibility checks. Do not use background client renewal.
- MySQL and Redis do not share a transaction. Preserve database rejection of closed Accounts when Redis revocation fails.
- Admin enrollment requires separate invitation and approval evidence. Operator commands must not issue tokens.
- Keep first-Admin evidence in Admin state. Serialize operator setup and manual seeding with the same database-scoped lock.
- Permit operator approval only for the sole pending Admin. Retained removed Admin rows prevent another first-Admin invitation.

## Change locations and decisions

- Bind owned ports and handlers in [ServiceProvider.php](ServiceProvider.php).
- Keep controllers and route registration inside their owning [ExampleApi](../../Infrastructure/Inbound/ExampleApi), [UserApi](../../Infrastructure/Inbound/UserApi) or [SystemAdminApi](../../Infrastructure/Inbound/SystemAdminApi) directory.
- ExampleApi reuses the USER realm, contact evidence, signing settings, sessions, and revocation state. Keep its Authentication and Identity controllers inside ExampleApi.
- Match controller modules to OpenAPI tags: Authentication, Identity, System, and SystemAdminApi Administration. Apply the [API controller organization rule](../../../../AGENTS.md#api-controller-organization).
- Use `Controllers/{TagName}/ModuleConfig.php` and `Controllers/{TagName}/Handlers/`, following ExampleApi. Keep `ROUTE_KEY` and `routeKey()` equal to the module's URL prefix.
- Register modules explicitly in `Controllers/ModuleRoutes.php`. Give each operation its own handler and use its OpenAPI operation ID as the route name.
- Use fluent route declarations. Define the realm once per group through `RealmPolicy`. Do not repeat realms in `RoutePolicy` or `ContactRoutePolicy`.
- Group protected routes with `RealmAuthentication` and `RealmPolicy`. Add `RealmPolicy` last so it runs before authentication.
- Apply `RealmPolicy` and write replay through `Idempotency` to public Authentication groups. Realm selection does not require bearer authentication.
- Keep action and contact policies on individual routes. Keep anonymous authentication, health, and preflight routes outside protected groups.
- Keep reusable identity HTTP input, dispatch, and policy support in [Adapters/Http/Identity](../../Infrastructure/Adapters/Http/Identity). Keep administration input inside SystemAdminApi's Administration module.
- Read [authentication rules](../../../../resources/platform/14-security.md) for token, login, and session changes.
- Read [contact verification](../../../../resources/platform/operations/contact-verification.md) for proof, quota, delivery, and recovery changes.
- Use [ADR-0036](../../../../resources/adr/0036-single-identity-and-access-context-and-global-accounts.md), [ADR-0049](../../../../resources/adr/0049-password-authentication-without-totp.md), [ADR-0051](../../../../resources/adr/0051-optional-email-verification-after-user-login.md), and [ADR-0052](../../../../resources/adr/0052-configurable-registration-verification.md) for current scope and decisions.
- Use [session recovery](../../../../resources/platform/operations/identity-session-recovery.md) for allowlist loss or restoration.

## Verification and maintenance

Run commands from the repository root. Select checks through [testing guidance](../../../../resources/platform/17-testing.md).
Use affected [context tests](Tests) and [identity HTTP tests](../../../../tests/Infrastructure/Inbound/IdentityApi).
Distinguish isolated tests from native MySQL and Redis checks. Service-backed checks require an identified, prepared target within existing authority.

Update this file when context ownership, invariants, contracts, or verification entry points change.
Keep detailed explanations in platform documents and decisions in architecture decision records. Check supersession status before using an older decision.

All identity HTTP writes require `Idempotency-Key`, including public authentication and contact operations.
Protected response replay requires current authentication. Shared replay claims do not wrap identity transactions.
See [HTTP idempotency](../../../../resources/platform/operations/http-idempotency.md) for encrypted responses and uncertain outcomes.

Renewal verification includes `Tests/Application/Sessions`, `Tests/Services/Sessions`, and `tests/Infrastructure/Inbound/IdentityApi/Sessions`.
Run `node --test tests/Guides/identity-session-client.test.mjs` for the shared browser and mobile client helper.
See [ADR-0053](../../../../resources/adr/0053-renewable-realm-sessions.md) for the renewal contract and compatibility boundaries.
