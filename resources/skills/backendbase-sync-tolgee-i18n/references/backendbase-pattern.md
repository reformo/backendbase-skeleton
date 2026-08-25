# Backendbase Tolgee synchronization pattern

## Two distinct operations

| Sync role | Backendbase operation | Target equivalent |
| --- | --- | --- |
| Local-to-remote | `sync-local-keys` creates missing Tolgee keys | Target remote-create command and scope |
| Remote-to-local | `sync-remote-keys` reads an export and replaces locale files | Target export and file-replacement command |
| Namespace | `service-name` prefixes remote keys | Target service or namespace prefix |
| Secret | `TOLGEE_API_KEY` | Target secret injection mechanism |
| Catalog | `resources/i18n/*.php` | Target tracked locale files |

Both scripts load:

- service prefix from `config/autoload/global.php` key `service-name`;
- `TOLGEE_API_KEY` as a required secret;
- `TOLGEE_API_URL`, defaulting to `https://app.tolgee.io`;
- locale files from `resources/i18n`.

Remote keys use this form:

```text
{service-name}.{local.dotted.key}
```

Local-to-remote flattens nested arrays, rejects empty or dotted source segments, and creates only missing keys. Remote-to-local filters by the service prefix, rejects non-string translations, expands dotted keys, renders PHP arrays, and replaces files only after every locale has rendered successfully.

## Authority boundary

- Help, source inspection, syntax checks, and worktree checks are read-only.
- Local-to-remote creates external project data. Ask immediately before running it.
- Remote-to-local replaces tracked files. Ask immediately before running it.
- A prior general request to edit translations is not authorization for either sync.
- Never place the secret in a committed file, response, log, or durable command example.

## Current source limitations and safety behavior

- Connect timeout is 10 seconds and request timeout is 30 seconds.
- The scripts validate status and JSON shape.
- Remote sync aborts without changes when a locale export contains no scoped keys.
- File replacement is atomic per locale, but only one writer should run.
- Live Tolgee must not be used by automated tests.
- Current `en-US.php` contains Turkish values; a sync can expose or replace that mismatch, so review the diff.

## Exact source provenance

- `bin/tolgee/sync-local-keys`
- `bin/tolgee/sync-remote-keys`
- `bin/tolgee/sync-support.php`
- `config/autoload/global.php`
- `resources/i18n/tr-TR.php`
- `resources/i18n/en-US.php`
- `resources/platform/21-i18n.md`

The target Tolgee project and secret-management rules control actual execution.
