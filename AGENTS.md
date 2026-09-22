---
name: karpathy-guidelines
description: Behavioral guidelines to reduce common LLM coding mistakes. Use when writing, reviewing, or refactoring code to avoid overcomplication, make surgical changes, surface assumptions, and define verifiable success criteria.
license: MIT
---

# Karpathy Guidelines

Behavioral guidelines to reduce common LLM coding mistakes, derived from [Andrej Karpathy's observations](https://x.com/karpathy/status/2015883857489522876) on LLM coding pitfalls.

Use task scope and observable risk to select the required guidance and checks.

## 0. Be understandable

**When explaining complex content, make good use of visualization.**


## 1. Think Before Coding

Resolve routine, reversible implementation details from current source and project conventions. State assumptions that affect the result.

Ask when an unresolved decision materially changes public behavior, compatibility, required platform choices, shared data, or external effects. Continue independent work while that decision is pending.

### Task-scoped guidance

- Read applicable `AGENTS.md` files. Reuse guidance already available in the current context.
- Use `resources/platform/README.md` to select guidance for the affected behavior and files. A prose-only correction needs no architecture or implementation skill.
- Select the smallest skill that adds a required procedure or invariant. Add another skill only for constraints not covered by the selected guidance.
- Read reference sections that resolve the current task. Do not load a complete reference or repository map only because it exists.
- Inspect target-project facts that remain unknown. Reuse verified discovery while the relevant files and scope remain unchanged.
- When scope expands, load guidance for the new surface and reassess decisions affected by that addition.

Platform design rules and applicable skill invariants remain requirements. Selective loading does not authorize a platform deviation. Report source conflicts before choosing an implementation that would violate a rule.

These loading, authorization, and verification rules govern repository skills, including older discovery lists and repeated approval or check instructions.

### Authorization

An implementation request authorizes the necessary local edits and known isolated checks within its scope. Review-only requests authorize inspection and findings.

Existing explicit authorization remains valid for the same action, target, and scope. A later skill confirmation step does not require another question. Ask again when those details materially change.

Verify the target before service-backed tests or operations. Do not assume a local command uses disposable data. Shared-database migrations, deployment, remote synchronization, and other external writes require explicit authority for their target and effects.

A migration draft authorizes a local artifact, not application to a database. A local translation edit does not authorize synchronization.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.
- Run PHPStan at level 8 for code changes. Fix errors introduced by the change. Report unrelated existing errors without expanding the task.
- Run `composer complexity` for code changes. Keep each function and method at cyclomatic complexity 12 or less.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

### Documentation synchronization

When code changes affect documented behavior, update every related document in the same change.

- Check `resources/platform`, `resources/docs`, and `resources/skills` for affected documentation.
- Update architecture, contracts, configuration, workflows, examples, operations, limitations, and file paths when applicable.
- Do not omit a required documentation change when the related code changes.

### Changelog synchronization

- Update the `Unreleased` section of `CHANGELOG.md` for every commit.
- Include the changelog entry in the same commit as the related code or documentation change.

The test: Every changed line should trace directly to the user's request.

### Skill authoring

For changes under `resources/skills`, read [the skill-authoring rules](resources/skills/AGENTS.md).

## 4. Goal-Driven Execution

For multi-step tasks, state the intended outcome and a brief plan with observable completion checks.

For implementation requests, continue through implementation, relevant verification, and correction of failures caused by the change. Do not stop for review while authorized work remains.

Use `resources/platform/17-testing.md` to select checks for changed behavior. Documentation-only edits need document checks, not PHP analysis or application tests.

One successful check can satisfy several selected skills. Repeat a check after a relevant edit, failure, environment change, or new evidence. Do not repeat it only because another checklist lists it.

Finish when the requested behavior works, required checks pass, and affected documentation is current. Confirm applicable platform and skill invariants for changed surfaces. Report concrete blockers, unrelated failures, and unverified results. Do not describe an unrun check as passed.

## 5. Object Calisthenics

1. One level of indentation per method — extract a private method when a loop or conditional would add another level.
2. Do not use `else` — handle guard and error cases with early returns.
3. Wrap primitives that carry business rules in dedicated value objects instead of passing raw strings, integers, or floats.
4. Use first-class collections — a collection class has the contained items as its only instance property and exposes collection behavior through methods.
5. Use at most one object access operator (`->` or `?->`) per statement to avoid traversing object graphs (Law of Demeter).
6. Do not abbreviate class, method, property, or variable names; use complete, intention-revealing names.
7. Keep classes small — no more than 150 lines per class file and no more than 10 class files per namespace directory.
8. Use no more than two instance properties per class.
9. Do not expose public properties or trivial getters and setters for callers to make business decisions; put that behavior on the object (Tell, don't ask).
10. Keep cyclomatic complexity at 12 or less for each function and method.


## 6. Coding Style

- Follow the Doctrine Coding Standard, which extends PSR-12.
- Unless otherwise specified, use `camelCase` for all names.
- Use `PascalCase` for class and namespace names.
- Use `snake_case` for database, schema, table, column, and index names.
- Use `kebab-case` for URL path parameter names, configuration values, and similar names. This rule does not apply to query parameters or request-body fields.
- Use `camelCase` for request query parameters and request-body fields.
- Use `camelCase` for response object attributes.

## 7. Defensive Programming

Defend at trust boundaries. Do not scatter redundant checks throughout trusted internal code.

### Validate External Input

- Treat HTTP input, command-line arguments, configuration, persisted data, file contents, and external-service responses as untrusted.
- Validate required fields, types, formats, ranges, sizes, and allowed values at the boundary before invoking domain logic.
- Reject invalid input explicitly; do not silently coerce, truncate, or substitute a default unless the contract requires it.
- Convert validated input into typed value objects or command objects so it cannot become invalid deeper in the application.

### Preserve Invariants

- Use precise parameter, property, and return types; avoid `mixed` and nullable types when the domain does not require them.
- Enforce domain invariants in constructors or named constructors so invalid objects cannot be created.
- Validate an operation completely before mutating the state.
- Use database transactions when multiple writes must succeed or fail as one unit.

### Fail Explicitly

- Never suppress errors with `@`, leave a `catch` block empty, or hide failure behind an unrelated fallback value.
- Catch only exceptions that can be recovered from, translated at an architectural boundary, or enriched with useful context.
- Throw specific exceptions and preserve the original exception as the previous exception when translating it.
- Use assertions for programmer invariants only, never as validation for user or external input.

### Isolate External Failures

- Set explicit timeouts for network and process calls.
- Retry only transient failures, use a bounded retry count with backoff, and retry state-changing operations only when they are idempotent.
- Validate external responses before using them; do not assume a successful status means the payload is complete or valid.
- Keep partial failure from leaving an inconsistent state; roll back or make the operation safely resumable.

### Protect Diagnostic Data

- Log enough context to diagnose a failure without logging credentials, secrets, access tokens, or unnecessary personal data.
- Return stable, safe error responses to clients; keep stack traces and internal implementation details out of public responses.

### Verify Failure Paths

- Test invalid, missing, boundary, and oversized inputs.
- Test dependency timeouts, malformed responses, transaction rollbacks, and retry exhaustion where those behaviors exist.
- Add a regression test for each fixed behavior defect that can be reproduced automatically. Do not add tests that only restate an implementation or prose edit.

## 8. Language

Use ASD-STE100 Simplified Technical English for all agent responses.

- Reply in English, regardless of the prompt language, unless the user explicitly requests a different response language.
- Always write documentation in English.
- Use approved, common words. Use each word with one meaning.
- Use the same term for the same item or action. Do not use synonyms only to vary the text.
- Use active voice. Use the imperative form for instructions.
- Give only one instruction in each sentence.
- Limit procedural sentences to 20 words and descriptive sentences to 25 words when practical.
- Keep paragraphs short. Use lists for sequences, alternatives, and groups of related facts.
- Avoid idioms, slang, jargon, contractions, and ambiguous pronouns.
- Define an abbreviation or an unfamiliar technical term at its first use.
- Preserve exact code identifiers, commands, paths, protocol terms, and quoted text when accuracy requires them.
- Use clear subject/verb/object constructions. Do not use cleft sentences, contrastive appositives, appended-glosses, or trailing clauses.
- Assume I may edit documents myself. Especially markdown documents.
- When writing markdown documents, don't include references to conversations or threads a reader would not know about.
