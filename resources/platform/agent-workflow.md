# Agent Workflow

The main agent works alone by default.
Use this workflow only when the user explicitly requests the Agent Workflow for the current task.
Use subagents only when the user explicitly requests subagents or the Agent Workflow for the current task.
The main agent completes planning, implementation, verification, and review alone when no such request exists.
Planning requests, code changes, task size, and complexity do not activate the workflow or subagents.

The main agent coordinates this workflow when requested.
Answer greetings, status questions, and simple information requests directly.

Use the configuration for the current client. Both clients follow the stages below when the workflow is explicitly requested.

## Codex configuration

Project defaults live in [.codex/config.toml](../../.codex/config.toml).
The main agent uses `gpt-6.1-sol` with `high` reasoning effort.

| Agent | Model | Reasoning effort | Responsibility |
| --- | --- | --- | --- |
| [planner](../../.codex/agents/planner.toml) | `gpt-6.1-sol` | `xhigh`, Fast mode | Prepare and revise the plan. |
| [test_analyst](../../.codex/agents/test_analyst.toml) | `gpt-6.1-sol` | `high`, Fast mode | Define test scope and audit implemented tests. |
| [plan_reviewer](../../.codex/agents/plan_reviewer.toml) | `gpt-6.1-sol` | `max` | Review the plan and test scope. |
| [implementer](../../.codex/agents/implementer.toml) | `gpt-6.1-sol` | `xhigh`, Fast mode | Implement changes, write tests, and run checks. |
| [code_reviewer](../../.codex/agents/code_reviewer.toml) | `gpt-6.1-sol` | `max` | Review actual code changes and regression risks. |

## Claude Code configuration

Project defaults live in [.claude/settings.json](../../.claude/settings.json).
Claude Code reads the [root instructions](../../AGENTS.md) and selected context instructions directly.
The main agent uses `sonnet` with `high` effort.
Model aliases resolve according to the installed Claude Code client and provider.
Sonnet supplies the coding roles. Opus supplies independent plan and code review.

| Agent | Model alias | Effort | Responsibility |
| --- | --- | --- | --- |
| [planner](../../.claude/agents/planner.md) | `sonnet` | `max` | Prepare and revise the plan. |
| [test_analyst](../../.claude/agents/test_analyst.md) | `sonnet` | `max` | Define test scope and audit implemented tests. |
| [plan_reviewer](../../.claude/agents/plan_reviewer.md) | `opus` | `high` | Review the plan and test scope. |
| [implementer](../../.claude/agents/implementer.md) | `sonnet` | `max` | Implement changes, write tests, and run checks. |
| [code_reviewer](../../.claude/agents/code_reviewer.md) | `opus` | `xhigh` | Review actual code changes and regression risks. |

The Claude planner uses normal mode.
Claude Fast mode applies to supported Opus models through a session setting.
The subagent format has no Fast mode field.
The native five-agent concurrency limit requires Claude Code 2.1.217 or later.
The main agent must keep at most five subagents active.

## Shared workflow

The `test_analyst` definition has two phases: `scope` and `audit`.
The main agent selects the phase in each task message.
Scope readiness does not establish that tests exist or have passed.

## Subagent boundaries

- Only the main agent starts agents and controls stage transitions.
- Subagents follow their assigned roles instead of restarting this workflow.
- Codex subagents disable further delegation through `agents.enabled = false`.
- The Codex `implementer` uses `workspace-write` mode. Other Codex roles use `read-only` mode.
- Claude subagent tool lists exclude delegation tools.
- Only the Claude `implementer` receives editing and shell tools. It inherits the session permission policy.
- Other Claude roles receive read tools and use `plan` permission mode.
- Subagents send required questions and follow-up work to the main agent.
- Apply the existing task-scoped loading, authorization, language, and verification rules throughout the workflow.

## 1. Prepare the task context

- Identify the current user request, subsequent corrections, project instructions, and constraints.
- Record the starting code reference and existing working-tree changes.
- Keep pre-existing changes separate from changes made for this task.
- Give each agent the current context required for its role.
- Resolve missing information from existing context before requesting a necessary user decision.

## 2. Prepare the plan and test scope

- Start `planner` with the request, project context, and constraints.
- Wait for the initial plan.
- Start `test_analyst` with `Phase: scope`.
- Supply the current plan, acceptance criteria, and relevant existing test infrastructure.
- Return required plan corrections to `planner`.
- Keep the plan and test scope together as one current reference.
- Repeat affected scope analysis only when requirements, acceptance criteria, or the test approach change.
- Do not repeat analysis only because the wording changes.

## 3. Review the plan

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

## 4. Implement and verify

- Give `implementer` the current request, reviewed plan, acceptance criteria, and agreed test scenarios.
- Have `implementer` complete the code, required tests, and authorized checks.
- Select test types through `resources/platform/17-testing.md` and the changed behavior.
- Do not require unit, functional, and end-to-end tests for every task.
- Prevent concurrent edits to the same files.
- Collect the changed-file summary, current code reference, scenario mapping, commands, actual results, and remaining gaps.
- Require affected documentation updates and changelog maintenance.

## 5. Review the code and audit the tests

- Start `code_reviewer` and `test_analyst` independently after implementation finishes.
- Run these reviews in parallel when capacity permits.
- Give `test_analyst` the task label `Phase: audit`.
- Supply both agents with the same current plan, acceptance criteria, test scope, and code state.
- Include the starting reference, task diff, new files, and implementation test report.
- `code_reviewer` checks code behavior, concrete defects, regressions, and behavior-related test gaps.
- `test_analyst` checks scenario coverage, assertions, test reliability, and current execution evidence.
- Wait for both review results before starting corrections.
- If code changes during review, repeat each affected review against the current code.

## 6. Combine findings and correct defects

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

## 7. Complete the task

- Confirm that the final `code_reviewer` and `test_analyst` audit decisions are both `READY`.
- Confirm that both decisions and required check results apply to the current code.
- Repeat affected checks and reviews after subsequent changes.
- Do not report completion while a required finding or verification gap remains.
- Report changes, actual verification results, review decisions, and concrete blockers.
- Continue within existing user authority after each successful stage.
- Do not request another approval only because planning or review has finished.
