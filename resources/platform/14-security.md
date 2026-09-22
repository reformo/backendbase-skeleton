# Authentication and Authorization

Authentication validates token identity and state. Authorization decides whether that identity can perform an operation.

## JWT

- Tokens use HMAC SHA-256 and a Base64 signing key.
- Validate signature, time, issuer, audience, token identifier, and Redis state.
- Redis enables immediate token revocation.
- The default alias is `USER`.
- The default issuer is `backendbase-api`.
- The default lifetime is 24 hours.

HTTP inputs depend on the `TokenIssuer` and `TokenValidator` application ports. The JWT adapter implements both ports.

`AuthorizationStore` isolates Redis-backed token state from token orchestration.

`POST /auth` validates a non-deleted account from `example_accounts`. It verifies the stored Argon2id password hash. It stores active privilege slugs in the JWT authorization state.

The protected `/accounts` module registers, revises, retires, and lists accounts. Registration hashes the supplied password with Argon2id before it reaches the command. Revision can replace active privilege grants. Retirement sets `deleted_at`, so later authentication cannot load the account.

Login, account revision, and account retirement use the same account-row write lock. Authentication holds the lock from the account read through Redis token storage. A change holds the lock from the authoritative account read through revocation and database commit. A waiting login reads the committed account state before checking credentials.

`AccountAuthenticationRepository::withAuthenticationLock()` and `AccountWriteRepository::withAccountLock()` own this persistence boundary. Doctrine starts an outermost transaction and locks the existing account row without changing its values. Existing transactions are rejected because they can contain stale snapshots. Account loads refresh the ORM record and its privilege grants. Memory adapters provide sequential callbacks and rollback behavior, not cross-process locks.

Revision and retirement still revoke Redis-backed authorization state before the database change. A revocation failure stops the change. A database failure rolls back account changes and closes the failed ORM unit of work. Revoked tokens remain revoked after rollback. Token-issue failures release the account lock. Do not issue account tokens from previously loaded snapshots outside this authentication flow.

`AuthorizationMiddleware` adds `authorizedUserId`, `authorizedUserData`, `clientTimezone`, `Acl`, and `AccessControl` request attributes.

`Acl::isAllowed()` accepts a named privilege, `full-privileges`, or the `system-admin` role. Denial uses status 403.

These commands and queries require `AccessControl`. Their application handlers check these privileges before protected work:

- `example.add`
- `example.change`
- `example.remove`
- `account.register`
- `account.revise`
- `account.retire`
- `account.list`

Keep privilege checks at the application handler boundary. Do not make this decision only in an HTTP controller.

Protect new endpoints by default. Make public policy explicit in routing and OpenAPI.

ExampleApi middleware adds `ValidateApiKey`. It validates `Backendbase-Api-Key` outside configured public paths and returns status 401 on failure.

Invalid credentials, API keys, and bearer tokens return status 401. ACL denials return status 403. Invalid time-zone headers return status 400.

Basis: `resources/docs/5-use-case-api.html`, `resources/docs/7-env-and-config.html`, `resources/docs/8-authentication-and-authorization.html`.
