# Local Identity Operator Procedures

Use these procedures only on an identified, authorized host and database.
Restrict operator access to trusted administrators. Applying an action requires a local root CLI process.
The CLI uses database transactions and appends evidence records. Preview is the default.
No operator action issues a token or bypasses ownership and actor approval gates.

## First Admin

1. Register a User Account through the USER API.
2. Verify contact ownership through its separate proof endpoint.
3. Preview the first-Admin invitation with the Account UUID, Admin login name, operator, and invitation evidence.
4. Apply the invitation through the authorized root process.
5. Record the returned Admin actor UUID.
6. Preview approval with that actor UUID and separate approval evidence.
7. Apply approval through the authorized root process.
8. Authenticate with the password through the ADMIN API.

Use `bin/backendbase identity:operator` with these options:

| Action | Target | Additional options |
| --- | --- | --- |
| `invite-first-admin` | Account UUID | `--login-name`, `--invitation` |
| `approve-first-admin` | Returned first-Admin actor UUID | `--approval` |

Every action requires `--action`, `--target-id`, and `--operator`.
Add `--apply` only after checking the preview and evidence.
Use references to authorization records. Do not put passwords or personal data in evidence options.

First-Admin invitation is available only before any Admin row exists, including deleted rows.
Unique bootstrap audit keys prevent concurrent invitations and repeated approval.
Ordinary Admin enrollment uses the authenticated invitation and approval API after bootstrap.

## Ownership proof key rotation

Inject active and previous proof keys under distinct identifiers.
The configuration retains the `encryptionKey` names for compatibility.
Keep the previous key until pending ownership proofs expire or receive authorized invalidation.
Restart supervised workers after runtime key injection changes.

TOTP enrollment, recovery, and factor rewrapping are removed.
Existing credential JSON fields remain unused. Operator evidence records remain stored.
