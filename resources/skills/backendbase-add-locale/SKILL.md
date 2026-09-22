---
name: backendbase-add-locale
description: Add a supported locale and complete local PHP dictionary to a Backendbase-style project. Use for a new language or region; do not use for one translation key or Tolgee synchronization.
---

# Add a locale

## Outcome

Add one exact locale file whose key shape, placeholders, runtime selection, approved wording, tests, and deployment packaging match the existing catalog.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect supported locales, dictionary discovery, default locale, catalog shape, selection tests, and deployment packaging.
3. Resolve the exact locale identifier, language, region, text direction, approved translations, and expected `Accept-Language` inputs.
4. Determine whether exact matching is sufficient or language-range negotiation is requested.
5. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not add a locale, fallback rule, or unapproved translated wording without user intent.

## Target-project adaptation

Use the target locale convention, default, dictionary format, key catalog, placeholder syntax, negotiation, packaging, and tests. Never copy Backendbase Turkish or English values, service prefixes, API keys, hosts, or example content as target translations.

## Workflow

1. Create one file named with the exact supported locale identifier.
2. Reproduce the full key structure and value kinds of the authoritative catalog.
3. Supply approved translations with identical placeholder names.
4. Update explicit locale allowlists or API documentation only when the target has them.
5. Test exact selection, unsupported input, default behavior, lookup, and placeholders.
6. Confirm the locale file is included in deployment artifacts.

## Backendbase invariants

- Runtime discovers `resources/i18n/*.php` files.
- A filename is the exact locale identifier.
- Catalog shape and placeholders match across locales.
- Current selection uses the first requested locale and exact filename matching.
- Unsupported or missing input selects the configured default behavior.
- Runtime performs no Tolgee request.
- A missing key does not fall back per key.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
php -l resources/i18n/{locale}.php
vendor/bin/phpunit tests/Shared/Services/SharedServicesTest.php
composer phpstan
composer complexity
composer cs-check
```

Compare key paths, value kinds, and placeholders across all locale files.

## Completion report

Report the locale identifier, source of approved wording, catalog parity, selection behavior, packaging check, tests, and blocked required checks.
