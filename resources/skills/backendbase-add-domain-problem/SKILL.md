---
name: backendbase-add-domain-problem
description: Add a typed Backendbase domain or application failure with a stable Problem Details contract and focused tests. Use for expected client-visible failures raised by business or application behavior; do not use for unexpected infrastructure errors, validation that belongs at an external boundary, or logging-only diagnostics.
---

# Add a Backendbase Domain Problem

## Outcome

Represent one expected failure as a pure domain error. Map it to a stable public HTTP Problem Details contract.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read the target project's `AGENTS.md` files.
2. Inspect the failure owner, existing problem base class, HTTP translation, nearest typed failure, and affected tests.
3. Search current public error codes and translations to prevent duplicate or conflicting meanings.
4. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.
5. Confirm the failure owner, status, stable code, public detail, and whether clients already depend on an existing contract.

## Target-project adaptation

Adapt namespace, error-code vocabulary, status, type, title, translation key, and approved additional fields. Do not copy Example messages, identity codes, internal exception text, file paths, traces, or sensitive data.

## Workflow

1. Decide whether the failure is an expected domain or application problem. Keep unexpected dependency failures at their adapter boundary.
2. Reuse an existing problem type when its public meaning is identical.
3. Otherwise add a small exception derived from the target project's pure domain-error base.
4. Add its status, type, code, and title mapping to the HTTP adapter.
5. Throw it at the boundary that owns the invariant or missing resource.
6. Add only public and non-sensitive additional data.
7. Test the exception contract and final HTTP Problem Details response when exposed through HTTP.

## Backendbase invariants

- Use the current pure `DomainException` base and its `create()` factory in an unmodified Backendbase project.
- Keep HTTP status, type, code, title, serialization, and translation out of the domain error.
- Add each public contract to `DomainErrorProblemDetailsMapper` in the Infrastructure HTTP adapter.
- Keep error codes stable and unique for client behavior.
- Keep stack traces, internal types, credentials, tokens, personal data, and storage details out of public fields.
- Preserve a previous exception when translating recoverable technical failure.
- Do not turn unexpected server failures into misleading client errors.
- Do not add HTTP transport logic to domain code.
- Do not add fields to public Problem Details without a requested contract.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Run the focused exception test and affected HTTP action or handler tests, then PHPStan level 8, the configured complexity check, and PHPCS. Run architecture tests when placement or dependencies changed.

## Completion report

Report the owner, stable public contract, throw site, safe additional data, tests, commands run, and blocked required checks.
