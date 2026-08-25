---
name: backendbase-add-runtime-configuration
description: Add or change environment-backed runtime configuration in a Backendbase-derived PHP project, including typed loading, validation, PHP-DI consumption, cache effects, and tests. Do not use only to edit deployment secrets.
---

# Add Backendbase runtime configuration

Read [references/backendbase-pattern.md](references/backendbase-pattern.md) before editing.

## Workflow

1. Read applicable `AGENTS.md` files and inspect Composer autoloading.
2. Trace the target project's environment loader, configuration merge order, settings contract, dependency providers, entry points, and cache behavior.
3. Find the closest configuration and service-resolution tests.
4. Define the setting owner, type, default, validation point, secret classification, and affected processes.
5. Change only the required configuration, service factory, local example, documentation, and tests.
6. Verify uncached loading and resolve each affected lazy service.

## Invariants

- Treat process configuration as untrusted input.
- Do not silently coerce invalid values.
- Keep live credentials out of source, examples, logs, and completion reports.
- Do not assume the Backendbase namespace, environment prefix, API slug, or setting keys.
- Clear caches and restart workers only when authorized in the target environment.

## Completion report

Report each added key, its type and validation, affected services and caches, changed documentation, tests run, and skipped checks.
