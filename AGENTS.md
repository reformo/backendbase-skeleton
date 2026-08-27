---
name: karpathy-guidelines
description: Behavioral guidelines to reduce common LLM coding mistakes. Use when writing, reviewing, or refactoring code to avoid overcomplication, make surgical changes, surface assumptions, and define verifiable success criteria.
license: MIT
---

# Karpathy Guidelines

Behavioral guidelines to reduce common LLM coding mistakes, derived from [Andrej Karpathy's observations](https://x.com/karpathy/status/2015883857489522876) on LLM coding pitfalls.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 0. Be understandable

**When explaining complex content, make good use of visualization.**


## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

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
- Run phpstan with level 8 for your changes and fix all errors.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

### Documentation synchronization

When code changes affect documented behavior, update every related document in the same change.

- Check `resources/platform`, `resources/docs`, and `resources/skills` for affected documentation.
- Update architecture, contracts, configuration, workflows, examples, operations, limitations, and file paths when applicable.
- Do not omit a required documentation change when the related code changes.

The test: Every changed line should trace directly to the user's request.

### Portable skill authoring

Skills under `resources/skills` are reusable code-generation guides for other projects. This repository provides verified examples, not literal templates.

When creating or updating a skill:

- Inspect current source, configuration, tests, and `resources/platform` before describing a pattern.
- Require discovery of the target project's architecture, namespaces, paths, framework, dependencies, configuration, contracts, security, test ownership, and deployment model.
- Adapt every instruction to the target project. Do not make target code depend on this repository.
- Treat Backendbase class names, paths, API names, schemas, headers, environment keys, hosts, credentials, fixtures, and sample data as role examples only.
- Keep repository-specific details in references as provenance or current limitations. Mark exact Backendbase rules as conditional on an unmodified Backendbase project.
- Keep each skill self-contained. 
- Prefer current code and tests over older narrative documentation when they conflict. Record important drift instead of copying it.
- Forward-test the skill against a differently named target project. Remove hidden Backendbase assumptions before completion.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

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
- Add a regression test for every fixed defect that can be reproduced automatically.

## 8. Language

Use ASD-STE100 Simplified Technical English for all English agent responses.

- Use English unless the user explicitly requests a different language.
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
