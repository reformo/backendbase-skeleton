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

## 9. Agent Workflow

The main agent works alone by default.
Use this workflow only when the user explicitly requests the Agent Workflow for the current task.
Use subagents only when the user explicitly requests subagents or the Agent Workflow for the current task.
The main agent completes planning, implementation, verification, and review alone when no such request exists.
Planning requests, code changes, task size, and complexity do not activate the workflow or subagents.

The main agent coordinates this workflow when requested.
Answer greetings, status questions, and simple information requests directly.

Use the configuration for the current client. Both clients follow the stages below when the workflow is explicitly requested.

### Codex configuration

Project defaults live in [.codex/config.toml](.codex/config.toml).
The main agent uses `gpt-6.1-sol` with `high` reasoning effort.

| Agent | Model | Reasoning effort | Responsibility |
| --- | --- | --- | --- |
| [planner](.codex/agents/planner.toml) | `gpt-6.1-sol` | `xhigh`, Fast mode | Prepare and revise the plan. |
| [test_analyst](.codex/agents/test_analyst.toml) | `gpt-6.1-sol` | `high`, Fast mode | Define test scope and audit implemented tests. |
| [plan_reviewer](.codex/agents/plan_reviewer.toml) | `gpt-6.1-sol` | `max` | Review the plan and test scope. |
| [implementer](.codex/agents/implementer.toml) | `gpt-6.1-sol` | `xhigh`, Fast mode | Implement changes, write tests, and run checks. |
| [code_reviewer](.codex/agents/code_reviewer.toml) | `gpt-6.1-sol` | `max` | Review actual code changes and regression risks. |

### Claude Code configuration

Project defaults live in [.claude/settings.json](.claude/settings.json).
[CLAUDE.md](CLAUDE.md) imports these shared instructions.
The main agent uses `sonnet` with `high` effort.
Model aliases resolve according to the installed Claude Code client and provider.
Sonnet supplies the coding roles. Opus supplies independent plan and code review.

| Agent | Model alias | Effort | Responsibility |
| --- | --- | --- | --- |
| [planner](.claude/agents/planner.md) | `sonnet` | `max` | Prepare and revise the plan. |
| [test_analyst](.claude/agents/test_analyst.md) | `sonnet` | `max` | Define test scope and audit implemented tests. |
| [plan_reviewer](.claude/agents/plan_reviewer.md) | `opus` | `high` | Review the plan and test scope. |
| [implementer](.claude/agents/implementer.md) | `sonnet` | `max` | Implement changes, write tests, and run checks. |
| [code_reviewer](.claude/agents/code_reviewer.md) | `opus` | `xhigh` | Review actual code changes and regression risks. |

The Claude planner uses normal mode.
Claude Fast mode applies to supported Opus models through a session setting.
The subagent format has no Fast mode field.
The native five-agent concurrency limit requires Claude Code 2.1.217 or later.
The main agent must keep at most five subagents active on earlier clients.

### Shared workflow

The `test_analyst` definition has two phases: `scope` and `audit`.
The main agent selects the phase in each task message.
Scope readiness does not establish that tests exist or have passed.

### Subagent boundaries

- Only the main agent starts agents and controls stage transitions.
- Subagents follow their assigned roles instead of restarting this workflow.
- Codex subagents disable further delegation through `agents.enabled = false`.
- The Codex `implementer` uses `workspace-write` mode. Other Codex roles use `read-only` mode.
- Claude subagent tool lists exclude delegation tools.
- Only the Claude `implementer` receives editing and shell tools. It inherits the session permission policy.
- Other Claude roles receive read tools and use `plan` permission mode.
- Subagents send required questions and follow-up work to the main agent.
- Apply the existing task-scoped loading, authorization, language, and verification rules throughout the workflow.

### 1. Prepare the task context

- Identify the current user request, subsequent corrections, project instructions, and constraints.
- Record the starting code reference and existing working-tree changes.
- Keep pre-existing changes separate from changes made for this task.
- Give each agent the current context required for its role.
- Resolve missing information from existing context before requesting a necessary user decision.

### 2. Prepare the plan and test scope

- Start `planner` with the request, project context, and constraints.
- Wait for the initial plan.
- Start `test_analyst` with `Phase: scope`.
- Supply the current plan, acceptance criteria, and relevant existing test infrastructure.
- Return required plan corrections to `planner`.
- Keep the plan and test scope together as one current reference.
- Repeat affected scope analysis only when requirements, acceptance criteria, or the test approach change.
- Do not repeat analysis only because the wording changes.

### 3. Review the plan

- Give `plan_reviewer` the request, current plan, test scope, and project constraints.
- `READY` permits implementation when no required plan defect remains.
- For `REVISE`, send required corrections to `planner`.
- Review the corrected plan again.
- Ask `test_analyst` to reassess affected scenarios when the test scope changes.
- For `NEEDS_INPUT`, resolve the missing information from available context when possible.
- Use at most two plan revision rounds.
- After two rounds, the main agent resolves remaining technical issues with concrete evidence.
- The revision limit does not remove unresolved required findings.
- Do not implement while required plan defects remain.

For a plan-only request, present the reviewed plan and test scope.
Stop before implementation and the final code and test reviews.

### 4. Implement and verify

- Give `implementer` the current request, reviewed plan, acceptance criteria, and agreed test scenarios.
- Have `implementer` complete the code, required tests, and authorized checks.
- Select test types through `resources/platform/17-testing.md` and the changed behavior.
- Do not require unit, functional, and end-to-end tests for every task.
- Prevent concurrent edits to the same files.
- Collect the changed-file summary, current code reference, scenario mapping, commands, actual results, and remaining gaps.
- Require affected documentation updates and changelog maintenance.

### 5. Review the code and audit the tests

- Start `code_reviewer` and `test_analyst` independently after implementation finishes.
- Run these reviews in parallel when capacity permits.
- Give `test_analyst` the task label `Phase: audit`.
- Supply both agents with the same current plan, acceptance criteria, test scope, and code state.
- Include the starting reference, task diff, new files, and implementation test report.
- `code_reviewer` checks code behavior, concrete defects, regressions, and behavior-related test gaps.
- `test_analyst` checks scenario coverage, assertions, test reliability, and current execution evidence.
- Wait for both review results before starting corrections.
- If code changes during review, repeat each affected review against the current code.

### 6. Combine findings and correct defects

- Combine duplicate findings from both reviews.
- Resolve conflicting findings with concrete evidence.
- Send one required correction list to `implementer`.
- For `REVISE`, require the relevant correction and affected checks.
- For `NEEDS_VALIDATION`, require the missing authorized check and current result evidence.
- For `NEEDS_INPUT`, resolve necessary information before continuing the affected stage.
- Repeat only reviews affected by the correction or new evidence.
- Reuse a previous result only when it remains valid for the current code.
- Return material scope, approach, or acceptance-criteria changes to `planner` and `plan_reviewer`.
- Reassess affected test scope through `test_analyst` when required.
- Do not restart all planning stages for a small code or test correction.
- Keep optional suggestions outside mandatory scope.
- Investigate repeated findings when another identical review round produces no progress.

### 7. Complete the task

- Confirm that the final `code_reviewer` and `test_analyst` audit decisions are both `READY`.
- Confirm that both decisions and required check results apply to the current code.
- Repeat affected checks and reviews after subsequent changes.
- Do not report completion while a required finding or verification gap remains.
- Report changes, actual verification results, review decisions, and concrete blockers.
- Continue within existing user authority after each successful stage.
- Do not request another approval only because planning or review has finished.
