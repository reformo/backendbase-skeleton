---
name: backendbase-sync-tolgee-i18n
description: Synchronize Backendbase-style local PHP dictionaries with Tolgee using the repository scripts. Use only for an explicit sync request; do not use for ordinary local translation or locale edits.
---

# Synchronize Tolgee translations

## Outcome

Run the requested local-to-remote or remote-to-local synchronization with explicit authority, protected credentials, one writer, bounded network work, and a reviewed result.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Resolve the requested synchronization direction, target project, namespace, locales, and create-or-replace effects from existing authority.
3. Inspect the exact sync scripts, service prefix, locale files, credential source, working-tree state, and current diff.
4. Check the selected script's create-or-replace behavior against that authority.
5. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not infer permission to mutate Tolgee or replace tracked locale files from a request to inspect or edit translations.

## Target-project adaptation

Use the target service prefix, Tolgee URL, secret injection, locale set, script paths, API contract, and review workflow. Never copy Backendbase API keys, service names, hosts, locale content, or shell examples containing secrets.

## Workflow

1. Run the selected script's help mode and inspect its implementation before live use. Reuse this evidence while the script and configuration remain unchanged.
2. Confirm the worktree has no overlapping locale changes from another writer.
3. Before writing, verify existing explicit authority covers the direction, target project, namespace, locales, and create-or-replace effects.
4. Ask only when that authority is missing or those details materially change. Continue independent read-only checks while awaiting a decision.
5. Supply the API key through an approved secret mechanism, not source, logs, or recorded command text.
6. Run only one writing sync process.
7. Check the exit code, changed keys or files, PHP syntax, catalog parity, and diff.
8. Stop on an empty export, malformed response, unexpected scope, or concurrent change.

## Backendbase invariants

- `sync-local-keys` creates missing remote keys only. It does not replace existing remote translations.
- `sync-remote-keys` replaces each local locale file from the remote service-prefixed export.
- An empty scoped export changes no files.
- Local replacement is atomic per file.
- HTTP connect and request timeouts are finite.
- Runtime translation remains local and does not call Tolgee.
- `TOLGEE_API_KEY` remains secret.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Safe checks before authorization:

```sh
bin/tolgee/sync-local-keys --help
bin/tolgee/sync-remote-keys --help
php -l bin/tolgee/sync-support.php
```

After an authorized sync:

```sh
php -l resources/i18n/{locale}.php
vendor/bin/phpunit tests/Shared/Services/SharedServicesTest.php
git diff -- resources/i18n
```

## Completion report

Report direction, approved scope, service prefix, created keys or replaced files, validation, diff summary, and any blocked required operation. Never report the API key.
