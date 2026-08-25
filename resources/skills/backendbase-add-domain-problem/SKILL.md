---
name: backendbase-add-domain-problem
description: Add a typed Backendbase domain or application failure with a stable Problem Details contract and focused tests. Use for expected client-visible failures raised by business or application behavior; do not use for unexpected infrastructure errors, validation that belongs at an external boundary, or logging-only diagnostics.
---

# Add a Backendbase Domain Problem

## Outcome

Represent one expected failure with a stable public status, type, code, title, detail policy, and safe optional data.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespace, layers, container and test layout, error base classes, HTTP translation, and the nearest typed failure.
3. Search current public error codes and translations to prevent duplicate or conflicting meanings.
4. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
5. Confirm the failure owner, status, stable code, public detail, and whether clients already depend on an existing contract.

## Target-project adaptation

Adapt namespace, error-code vocabulary, status, type, title, translation key, and approved additional fields. Do not copy Example messages, identity codes, internal exception text, file paths, traces, or sensitive data.

## Workflow

1. Decide whether the failure is an expected domain or application problem. Keep unexpected dependency failures at their adapter boundary.
2. Reuse an existing problem type when its public meaning is identical.
3. Otherwise add a small exception derived from the target project's current domain-problem base.
4. Define stable status, type, code, and title constants.
5. Throw it at the boundary that owns the invariant or missing resource.
6. Add only public and non-sensitive additional data.
7. Test the exception contract and final HTTP Problem Details response when exposed through HTTP.

## Backendbase invariants

- Use the current `DomainException` contract and its `create()` factory in an unmodified Backendbase project.
- Keep error codes stable and unique for client behavior.
- Keep stack traces, internal types, credentials, tokens, personal data, and storage details out of public fields.
- Preserve a previous exception when translating recoverable technical failure.
- Do not turn unexpected server failures into misleading client errors.
- Do not add HTTP transport logic to domain code.
- Do not add fields to public Problem Details without a requested contract.

## Verification

Run the focused exception test and affected HTTP action or handler tests, then PHPStan level 8 and PHPCS. Run architecture tests when placement or dependencies changed.

## Completion report

Report the owner, stable public contract, throw site, safe additional data, tests, commands run, and skipped checks.
