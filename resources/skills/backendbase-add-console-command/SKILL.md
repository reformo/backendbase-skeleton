---
name: backendbase-add-console-command
description: Add or change a Symfony Console command in a Backendbase-derived PHP project, including input validation, PHP-DI registration, focused tests, and runtime classification. Do not use for CQRS commands or HTTP endpoints.
---

# Add a Backendbase console command

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, console bootstrap, container registration, tests, and the nearest working command.
3. Classify the command as finite maintenance work or a supervised long-running worker.
4. Define its inputs, validation, exit codes, output, dependencies, and side effects before writing code.
5. Add the smallest command, registration entry, and focused `CommandTester` tests.
6. Run focused tests, architecture tests when boundaries change, PHPStan level 8, the configured complexity check, and PHPCS.

## Invariants

- Keep Symfony Console and container code outside the domain layer.
- Validate command-line input before invoking application or infrastructure work.
- Return meaningful `SUCCESS`, `INVALID`, or `FAILURE` codes.
- Register the command through the target project's existing container mechanism.
- Do not install a cron entry, supervisor unit, or run a destructive command without explicit authorization.

## Completion report

Report the command name, changed files, registration path, runtime model, tests run, and any blocked required check and its blocker.
