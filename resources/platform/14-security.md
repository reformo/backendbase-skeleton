# API Keys and Access Control

ExampleApi protects metadata and all Example reads and writes with an API key. Liveness and readiness remain public.

## HTTP policy

- `ValidateApiKey` validates `Backendbase-Api-Key` against `EXAMPLE_API_KEY`.
- API context middleware supplies `apiName`, `apiKeyHeaderName`, and `useIdentifierAsApiKey` before validation.
- Missing or invalid API keys return status 401 before controller dispatch.
- Example write commands and handlers do not require bearer identity or named privileges.
- Protect new consumer endpoints with the existing API-key middleware by default.
- Declare anonymous policy explicitly in routing and OpenAPI.
- Do not log API keys or return them in diagnostics.

The IdentityAndAccess context, token services, account operations, and Greeting HTTP routes have been removed. The API has no built-in user authentication flow.

## Internal access control

Shared `AccessControl` remains a framework-independent application contract. Internal `QueueGreeting` requires it and checks `example.add` before its integration-event transaction. Its caller must supply an implementation of this contract. Tests use interface doubles for allowed and denied paths.

Keep required privilege decisions at the application-handler boundary. Do not make these decisions only in a delivery adapter. A shared `ResourceAccessForbidden` error still maps to status 403.

## Removed database artifacts

The IdentityAndAccess migration, privilege seeder, and seeder test have been removed. New databases do not create account or privilege tables.

Databases that executed the removed migration require a schema and migration-history adoption plan before deployment. See [the database guide](../docs/9-persistence-and-database.html#migrations). Apply database changes only with explicit authority for the target database.

## Current limits

The API-key middleware has public path prefixes outside the current route set. Shared OpenAPI headers are not all enforced at runtime. The CORS header list omits `Accept-Language`.

Basis: `resources/docs/5-use-case-api.html`, `resources/docs/7-env-and-config.html`, `resources/docs/8-authentication-and-authorization.html`.
