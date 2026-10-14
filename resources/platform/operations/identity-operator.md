# Local Identity Operator Procedures

Use these procedures only on an identified, authorized host and database.
Restrict operator access to trusted administrators. Applying an action requires a local root CLI process.
The CLI uses database transactions and appends evidence records. Preview is the default.
No operator action issues a token or bypasses ownership and TOTP gates.

## First Admin

1. Register a User Account through the USER API.
2. Verify contact ownership through its separate proof endpoint.
3. Preview the first-Admin invitation with the Account UUID, Admin login name, operator, and invitation evidence.
4. Apply the invitation through the authorized root process.
5. Record the returned Admin actor UUID.
6. Preview approval with that actor UUID and separate approval evidence.
7. Apply approval through the authorized root process.
8. Enroll TOTP if the Account has no enrolled factor.
9. Complete password and TOTP authentication through the ADMIN API.

Use `bin/backendbase identity:operator` with these options:

| Action | Target | Additional options |
| --- | --- | --- |
| `invite-first-admin` | Account UUID | `--login-name`, `--invitation` |
| `approve-first-admin` | Returned first-Admin actor UUID | `--approval` |
| `recover-totp` | Account UUID | `--invitation` for recovery request evidence, `--approval` for separate authorization evidence |
| `rewrap-totp` | Account UUID, including a historical Account | `--approval` for rotation evidence |

Every action requires `--action`, `--target-id`, and `--operator`.
Add `--apply` only after checking the preview and evidence.
Use references to authorization records. Do not put passwords, TOTP secrets, or personal data in evidence options.

First-Admin invitation is available only before any Admin row exists, including deleted rows.
Unique bootstrap audit keys prevent concurrent invitations and repeated approval.
Ordinary Admin enrollment uses the authenticated invitation and approval API after bootstrap.

## Lost TOTP

Record the recovery request and separate authorization evidence before applying `recover-totp`.
The action increments the Account credential version and clears active and pending factors.
The action also clears contact ownership verification and MFA failure counters.
Existing challenges and sessions fail the current database credential gate immediately.
Redis records expire normally. A Redis outage cannot restore the old database authority.

The Account holder must use the existing password to request a new ownership proof.
The Account holder must verify that proof and enroll a new TOTP factor.
The Account holder must complete password and TOTP login before receiving a new session.
The operator procedure does not reset a lost password or change an Admin approval decision.

## Factor key rotation

Inject active and previous encryption keys under distinct identifiers.
Run `rewrap-totp` for each persisted Account before retiring the previous key.
Include historical Accounts. Their encrypted factors can still be required by recovery obligations.
The action decrypts active and pending factor ciphertext and encrypts it with the active key.
The action preserves the accepted TOTP step, credential version, password hash, and ownership state.

Inspect every stored key prefix after the complete run.
Do not retire the previous key while required backups still contain its ciphertext.
Pending challenge proof hashes require their original key until expiry or explicit invalidation.
Restart supervised workers after runtime key injection changes.
