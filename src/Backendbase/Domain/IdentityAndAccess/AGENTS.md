# IdentityAndAccess Instructions

Apply the [root rules](../../../../AGENTS.md) and relevant [platform guidance](../../../../resources/platform/README.md).
This context owns global Accounts, User and SystemAdmin actors, contact evidence, credentials, tokens, and sessions.

## Context rules

- Keep Account identity separate from actor identity. One Account can own at most one actor of each type, including removed records.
- Keep USER and ADMIN authentication realms separate. The JWT `sub` identifies the actor; `accountId` identifies the Account.
- Keep contact ownership, password authentication, and Admin approval as separate checks.
- Require both email and mobile proofs before User registration. Validate matching contacts, shared context, unused evidence, and freshness after both locks.
- Consume both proofs atomically with Account, User, and credential creation. Use the 12-hour evidence window from ADR-0050.
- Password login returns a session after the required gates. TOTP enrollment and second-step login are absent under ADR-0049.
- Require both token and session allowlist records and current database identity state for authentication.
- MySQL and Redis do not share a transaction. Preserve database rejection of closed Accounts when Redis revocation fails.
- Admin enrollment requires separate invitation and approval evidence. Operator commands must not issue tokens.

## Change locations and decisions

- Bind owned ports and handlers in [ServiceProvider.php](ServiceProvider.php).
- Keep controllers and route registration inside their owning [ExampleApi](../../Infrastructure/Inbound/ExampleApi), [UserApi](../../Infrastructure/Inbound/UserApi) or [SystemAdminApi](../../Infrastructure/Inbound/SystemAdminApi) directory.
- ExampleApi reuses the USER realm, contact evidence, signing settings, sessions, and revocation state. Keep its Authentication and Identity controllers inside ExampleApi.
- Match controller modules to OpenAPI tags: Authentication, Identity, System, and SystemAdminApi Administration. Apply the [API controller organization rule](../../../../AGENTS.md#api-controller-organization).
- Use `Controllers/{TagName}/ModuleConfig.php` and `Controllers/{TagName}/Handlers/`, following ExampleApi. Keep `ROUTE_KEY` and `routeKey()` equal to the module's URL prefix.
- Register modules explicitly in `Controllers/ModuleRoutes.php`. Give each operation its own handler and use its OpenAPI operation ID as the route name.
- Use fluent route declarations. Define the realm once per group through `RealmPolicy`. Do not repeat realms in `RoutePolicy` or `ContactRoutePolicy`.
- Group protected routes with `RealmAuthentication` and `RealmPolicy`. Add `RealmPolicy` last so it runs before authentication.
- Apply only `RealmPolicy` to public Authentication groups. Realm selection does not require bearer authentication.
- Keep action and contact policies on individual routes. Keep anonymous authentication, health, and preflight routes outside protected groups.
- Keep reusable identity HTTP input, dispatch, and policy support in [Adapters/Http/Identity](../../Infrastructure/Adapters/Http/Identity). Keep administration input inside SystemAdminApi's Administration module.
- Read [authentication rules](../../../../resources/platform/14-security.md) for token, login, and session changes.
- Read [contact verification](../../../../resources/platform/operations/contact-verification.md) for proof, quota, delivery, and recovery changes.
- Use [ADR-0036](../../../../resources/adr/0036-single-identity-and-access-context-and-global-accounts.md), [ADR-0049](../../../../resources/adr/0049-password-authentication-without-totp.md), and [ADR-0050](../../../../resources/adr/0050-contact-verification-before-user-registration.md) for current scope and decisions.
- Use [session recovery](../../../../resources/platform/operations/identity-session-recovery.md) for allowlist loss or restoration.

## Verification and maintenance

Run commands from the repository root. Select checks through [testing guidance](../../../../resources/platform/17-testing.md).
Use affected [context tests](Tests) and [identity HTTP tests](../../../../tests/Infrastructure/Inbound/IdentityApi).
Distinguish isolated tests from native MySQL and Redis checks. Service-backed checks require an identified, prepared target within existing authority.

Update this file when context ownership, invariants, contracts, or verification entry points change.
Keep detailed explanations in platform documents and decisions in architecture decision records. Check supersession status before using an older decision.
