---
name: backendbase-add-translation
description: Add or change local translation keys and placeholders in a Backendbase-style PHP dictionary. Use for catalog content; do not use to add a locale or synchronize with Tolgee.
---

# Add a translation

## Outcome

Add one stable dotted translation key with matching structure and placeholders in every supported locale, without adding runtime network work.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the affected dictionaries, supported locales, key structure, placeholders, and relevant catalog checks. Inspect loading or callers only when their behavior is affected.
3. Resolve the key owner, intended text, placeholder names, supported locales, and whether the value is a string or structured array.
4. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not invent a new locale, key namespace, remote synchronization, or wording that requires product approval.

## Target-project adaptation

Use the target locale list, default locale, dictionary format, key ownership, placeholder syntax, fallback behavior, and tests. Never copy Backendbase service prefixes, locale content, API keys, hosts, or example messages.

## Workflow

1. Choose a stable dotted key under the owning catalog section.
2. Add the same key path to every supported locale.
3. Keep placeholder names and value kind identical across locales.
4. Preserve valid PHP return arrays and target formatting rules.
5. Test direct lookup, placeholder replacement, and missing-key behavior when affected.
6. Review all changed locale values with the user-provided meaning.

## Backendbase invariants

- Runtime reads local PHP dictionaries only.
- Runtime does not call Tolgee or Redis for translation.
- Locale filenames are exact locale identifiers.
- All locale files have matching key shape and placeholder names.
- Missing keys return the locale-qualified key.
- There is no per-key fallback to the default dictionary.
- Values that can contain sensitive data are not written into source catalogs.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
php -l resources/i18n/{locale}.php
vendor/bin/phpunit tests/Shared/Services/SharedServicesTest.php
composer phpstan
composer complexity
composer cs-check
```

Compare key paths and placeholders across every locale, not only the changed file.

## Completion report

Report the key, locales changed, placeholders, lookup tests, catalog consistency check, and blocked required checks.
