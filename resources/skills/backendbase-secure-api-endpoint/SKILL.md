---
name: backendbase-secure-api-endpoint
description: Protect an existing API operation with the target project's established API-key, identity, and access-control mechanisms. Do not create an identity provider or credential flow.
---

# Secure an API endpoint

## Outcome

Apply the requested security policy to one operation. Keep routing, application decisions, OpenAPI, and failure tests aligned.

## Task-scoped discovery

1. Read applicable target-project instructions.
2. Inspect the affected route, middleware, application checks, public contract, and focused tests.
3. Discover the owning API, selection mechanism, middleware order, configuration keys, headers, and security schemes.
4. Identify available API-key, identity, and access-control mechanisms. Inspect token settings and state only when the target uses tokens.
5. Resolve the operation's identity requirement, privilege requirement, public exceptions, and failure status contract.
6. Read only needed sections of [the security pattern](references/backendbase-pattern.md).

Reuse verified project facts while their source remains unchanged. Report a required mechanism that the target does not provide. Do not invent a credential flow.

## Target-project adaptation

Use target namespaces, routes, configuration, identity models, ports, headers, and test helpers. Backendbase paths and names are role examples. Never copy signing data, API keys, tokens, hosts, or user fixtures.

Ask only when an unresolved security decision materially changes public behavior. Existing authority for the same policy remains valid.

## HTTP idempotency

In an unmodified Backendbase project, every POST, PUT, PATCH, and DELETE operation requires `Idempotency-Key`.
GET, HEAD, and OPTIONS need no key. `X-Request-Id` is separate tracing metadata.

Read [HTTP idempotency](../../platform/operations/http-idempotency.md) before changing a write route's security policy.
Authenticate protected requests before replay. Public Authentication replay follows realm selection.
Verify that expired or revoked credentials cannot obtain a protected stored response.
For other targets, discover their replay contract before applying this policy.

## Workflow

1. State whether the operation requires anonymous access, an API key, user identity, or a named privilege.
2. Apply only the established mechanisms required by that policy.
3. Pass typed access control into an application contract when a named privilege is required.
4. Enforce that privilege at the application-handler boundary before protected work.
5. Keep credential parsing and validation in the transport boundary.
6. Declare the same security requirements and failure responses in OpenAPI.
7. Use `security: []` only when all applicable runtime layers permit anonymous access.
8. Align runtime headers, configured CORS, and maintained Bruno requests.
9. Regenerate and validate source and generated contracts.
10. Test allowed and denied paths through the actual route stack.

Test expired or revoked credentials only when the selected mechanism provides those states.

## Backendbase reference policy

- Backendbase ExampleApi requires an API key for metadata, Authentication, Identity, and all Example operations.
- Its protected Example and Identity groups also require the USER realm. `getExampleByCriteria` and Authentication operations require no bearer token.
- Liveness and readiness are public.
- Backendbase ExampleApi and UserApi share USER account and session flows. SystemAdminApi uses the separate ADMIN realm.
- Define each group's realm once through `RealmPolicy`. Protected groups also apply `RealmAuthentication`; public Authentication groups apply `RealmPolicy` and write replay through `Idempotency`.
- Keep endpoint action and contact policies independent of realm selection. Verify group middleware order through actual requests.
- Shared `AccessControl` remains available for application privilege decisions.
- Internal `QueueGreeting` checks `example.add` before its transaction. It has no HTTP route.
- Other projects must use their discovered security policy and equivalent application boundary.
- An API-key-only operation is protected. It does not establish a user identity.
- Keep API-key validation, identity validation, and application privileges as separate decisions when they exist.
- Do not log secrets or unnecessary personal data.

## Verification

Select checks for the changed behavior and target policy. Adapt every command and path to the target runner. Reuse successful checks while their inputs remain unchanged.

Backendbase route evidence uses `tests/Infrastructure/Inbound/ExampleApi`, `tests/Infrastructure/Inbound/IdentityApi`, and `tests/Infrastructure/Adapters/Http/Middleware`. Internal greeting policy uses its context-owned handler tests. These are examples, not required target paths.

Run focused boundary tests, source and generated OpenAPI validation, and the target's required analysis, complexity, and style checks. A middleware unit test alone does not prove route protection.

## Completion report

Report each applied security layer, request context, status contract, actual route evidence, and concrete verification blockers.
