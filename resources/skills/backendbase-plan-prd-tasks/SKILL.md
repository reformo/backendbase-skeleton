---
name: backendbase-plan-prd-tasks
description: Turn a product requirements document into an ordered backend task plan with dependencies, blocker issues, requirement coverage, and completion checks. Use for backend backlog planning; exclude frontend and client implementation tasks.
---

# Plan Backend Tasks from a PRD

Convert a product requirements document (PRD) into tasks that an Implementer can complete in dependency order. Plan from confirmed behavior and current target source.

## Establish scope and evidence

- Read the target project's instructions and the requested PRD. Record its path and revision or content hash when available.
- Use the target platform entry point to select relevant architecture, contracts, persistence, security, and verification guidance.
- Inspect only the source, configuration, tests, and provider capabilities needed to establish the starting point. Reuse current verified evidence.
- Distinguish implemented behavior, reusable components, missing work, documentation drift, and unknown facts. A package or import does not prove working behavior.
- Discover the target's namespaces, delivery surfaces, storage, composition, and test commands. Do not copy the reference project's paths or names.
- Preserve the PRD's identifiers, exact limits, state transitions, error priorities, and exclusions. Mark proposed contracts and unresolved choices explicitly.

Keep the plan within backend scope. Include server APIs, authorization, persistence, integrations, response state, and server-enforced continuation rules where required.

For mixed client/backend criteria, assign the server responsibility to a backend task. Mark the remaining client responsibility as excluded. Exclude screens, navigation, client token storage, and client interactions from the task list. Do not append a separate client backlog or count backend evidence as proof of a complete mixed criterion.

A planning request produces a proposed plan. Create or update task records only when requested, through the target project's supported workflow. Keep proposed labels distinct from existing task identifiers. Implementation and external operations require their applicable authority.

## Build tasks around outcomes

1. Identify the actor goals, complete use cases, main scenarios, and relevant extensions.
2. Select the first complete backend transaction that gives an observable result through its delivery boundary.
3. Add later outcomes, alternate paths, and failure recovery according to their dependencies and risk.
4. Include each outcome's required domain behavior, application operation, persistence, delivery contract, tests, and documentation in that task.
5. Add small enabling tasks only when several outcomes need the same prerequisite. Name their consumers and measurable completion checks.
6. Add a final task for combined lifecycle evidence and remaining delivery checks. Keep local behavior tests within the tasks that implement them.

Name each task with a verb and a concrete outcome. Give one independently verifiable outcome or shared prerequisite to each task. Split work when it contains separate goals, unrelated file areas, or materially different decision gates. Choose the task count from the PRD and current implementation.

Include missing foundation work in the plan. Create only the structure needed by the next outcomes. Do not plan a complete framework or an empty file for every possible layer.

Include the security and failure rules needed for each slice to remain correct. Do not defer those rules to final integration. Distinguish completion of an increment from readiness of the full feature.

## Order dependencies and decision gates

- Give each task a stable proposed label and explicit prerequisites. Preserve existing labels when revising an accepted plan.
- Record required predecessor tasks as `dependsOn`. Identify the output each predecessor must provide and its acceptance evidence.
- Keep preferred delivery order separate from required dependencies. A row appearing earlier does not make it a prerequisite.
- Order tasks so every prerequisite precedes its consumer. Remove cycles, references to missing tasks, and unexplained dependencies.
- Identify the first complete user journey and later completion milestones. State which tasks can proceed independently after shared prerequisites.
- For each unresolved material choice, identify the decision, its owner when known, and the tasks it blocks.
- Keep a decision gate on affected tasks. Continue unrelated planning while that decision remains pending.
- Resolve routine implementation choices from current source and target conventions. Ask only when the unresolved choice materially changes behavior, compatibility, architecture constraints, shared data, or external effects.
- Put independent plan review before dependent implementation when target policy requires it. Keep implementation review after the behavior and checks exist.

Check dependencies that a success-only plan can miss:

- Storage mappings and ownership before dependent persistence behavior.
- Authoritative eligibility and permission checks at the final write boundary.
- Database constraints and transaction ownership for concurrent changes.
- A target-approved result path for writes that return generated values. Preserve command and query semantics.
- Retry authority, duplicate requests, and response loss after a committed write.
- Provider failure, bounded retry, compensation, and cleanup required by the chosen integration.
- Actual container, route, bus, or worker registration before claiming a usable delivery path.

## Track blocker issues and readiness

Record an issue as a blocker when it prevents a task from starting or meeting a required completion check. Link it through `blockedBy`. Distinguish a current blocker from a possible future risk. Record risk mitigation without blocking unrelated work.

Read existing task and issue records through the target's supported workflow when they are relevant and accessible. Reuse their actual identifiers and links. Use local proposed labels such as `B01` for new blocker records. Do not invent tracker identifiers or present proposed issues as created issues.

For each blocker, record its kind, condition, affected task IDs, owner when known, current status, and source evidence. Relevant kinds include unresolved decisions, contract conflicts, missing technical capabilities, external dependencies, unavailable environments, and required approvals. Apply the target's actual approval rules.

State the exact condition and evidence needed to resolve the blocker. Create a proposed investigation or prerequisite task when work can resolve it. Link that work to the blocker and its consumers. Keep mitigation and independent work visible.

Derive proposed readiness from current evidence:

- **Ready:** required predecessor outputs are accepted and no applicable blocker remains open.
- **Waiting for dependencies:** one or more required predecessor outputs are incomplete.
- **Blocked:** a recorded unresolved issue prevents the named start or completion condition. State which condition it prevents.
- **Unknown:** required external status or acceptance evidence is unavailable. Identify the check needed before dispatch.

Use the target's equivalent states when they exist. Keep readiness as a planning assessment unless an authorized workflow updates canonical task state. A later-stage blocker can permit earlier independent work; state that boundary explicitly.

After a dependency or blocker changes, reassess affected descendants and delivery milestones. Do not unblock a task solely because an issue was closed; verify its resolution evidence. Check shared files, contracts, schemas, and resources before recommending concurrent implementation. Separate work that can run independently from work that needs coordinated integration.

## Define completion and coverage

For each task, record:

| Field | Required content |
| --- | --- |
| ID and title | A stable label and an action that states the outcome. |
| Kind | Behavior, shared prerequisite, decision, or combined verification. |
| Scope | Included backend behavior and the relevant boundaries. |
| Source | Requirement, use-case/scenario, and acceptance identifiers supplied by the PRD. |
| Dependencies | Required predecessor IDs, consumed outputs, and acceptance evidence. Keep preferred order separate. |
| Blockers and readiness | `blockedBy` issue IDs, the affected start/completion condition, and evidence-based readiness. |
| Affected areas | Verified target locations and proposed additions, when known. Name concrete files when requested or needed to remove ambiguity. |
| Completion check | Observable success, relevant failures, and the evidence needed to accept the task. |

Select checks from the target's existing commands and policies. Add boundary-time, concurrency, rollback, revocation, retry, or cleanup evidence where the behavior requires it. Use real composition checks when runtime wiring changes. Specify the prepared target needed for service-backed checks.

Keep schema mapping and repository checks before migration generation. Keep migration drafting and review separate from application to a database.

Include affected API contracts, generated specifications, maintained executable examples, and documentation in the same task. Apply the target's changelog policy.

Map every confirmed requirement and acceptance criterion to a backend task, a stated decision gate, or an explicit scope exclusion. Split mixed criteria into their backend and excluded client responsibilities. Treat a decision gate as unresolved coverage until a delivery task owns the behavior. Investigate unmapped identifiers and overlapping task ownership.

## Deliver the plan

Lead with backend scope and the current implementation baseline. Present an ordered table with task IDs, titles, dependencies, blockers, readiness, and completion checks. Add scope, source identifiers, and affected areas in columns or concise task details.

Follow the table with:

- The first complete journey and any later delivery milestones.
- Independent work, coordination needs, and the prerequisite chain that controls the next milestone.
- A blocker register with affected tasks, owners, status, and explicit resolution evidence.
- A compact coverage map, including mixed criteria and client-only exclusions without client tasks.
- Sources, assumptions, and checks that remain unverified.

Use the requested output format and destination. Keep an ordinary planning answer in the response unless persistence was requested. Do not impose a fixed number of tasks, a file-count target, a particular database, or a session architecture.

Estimate effort only when requested. Include known missing foundations, required tests, and documentation in the base scope. State uncertainty and waiting time separately. Exclude client effort.

Before delivery, check that dependencies are acyclic and task and blocker references resolve. Verify readiness, explicit unblock conditions, requirement coverage, and observable completion checks. Confirm that no client implementation task remains.
