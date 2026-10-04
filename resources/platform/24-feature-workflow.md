# Feature Change Workflow

Start from the user-visible outcome. Add only the layers that the requested behavior needs.

## Select guidance

Follow the task-scoped loading and authorization rules in the root `AGENTS.md`. Use `resources/platform/README.md` to resolve affected constraints and unknown project facts.

Use the feature skill for a change across several layers. Add a narrow skill only for a required procedure or invariant not already covered. Read the reference sections relevant to the change.

When scope expands, inspect the added surface and reassess affected decisions. Reuse valid discovery and verification for unchanged surfaces.

Platform rules remain requirements. Report a conflict before choosing a deviation. Existing explicit authority remains valid for the same action, target, and scope.

## Plan backend tasks from a PRD

Use [backendbase-plan-prd-tasks](../skills/backendbase-plan-prd-tasks/SKILL.md) to convert a product requirements document (PRD) into an ordered backend task plan.

- Start from current source and complete backend outcomes. Keep shared prerequisites small and name their dependent tasks.
- Include the required contracts, persistence, tests, and documentation in each behavior task.
- Separate required dependencies from preferred delivery order. Record blocker issues, their affected tasks, owners, status, and resolution evidence.
- Derive task readiness from accepted prerequisite outputs and current blockers. Reassess dependent tasks when those conditions change.
- Record completion checks and coverage of the PRD identifiers. Block only work that depends on an unresolved condition.
- Include server responsibilities from mixed requirements. Record client-only responsibilities as scope exclusions without creating client tasks.
- Keep the output as a proposed plan unless task creation or implementation was requested.

## Deliver the requested behavior

- Define the observable outcome and completion checks. Resolve material contract or policy decisions before dependent implementation.
- Add domain behavior, commands or queries, ports, handlers, and adapters only where the feature requires them.
- Align affected HTTP routes, authorization, OpenAPI, and maintained Bruno examples. Add transactional integration events only when required.
- Establish mapping and repository behavior before generating an authorized schema migration. Review its SQL and release metadata. Apply it only with authority for the identified database.
- Update affected documentation and the `Unreleased` section of `CHANGELOG.md` in the same commit.
- Select verification through `17-testing.md`. Reuse each successful result across selected skills while its relevant inputs remain unchanged.

Every changed line must support the requested outcome. Do not refactor adjacent code or add speculative abstractions.

Continue through implementation, required checks, and correction of failures caused by the change. Finish when the requested behavior, applicable rules, and affected documentation are verified.

Report concrete blockers and required checks that could not run. Do not report unrelated checks as skipped work or request review while authorized work remains.

Basis: `resources/docs/0-project.html` and all topic guides.
