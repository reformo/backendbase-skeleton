---
name: backendbase-implement-feature
description: Implement a feature across several architecture layers in a Backendbase-style project. Use for coordinated changes; use a narrower skill for a single artifact.
---

# Implement a Cross-Layer Feature

Deliver the requested behavior, aligned contracts, relevant verification, and affected documentation. Planning or review requests do not authorize implementation or external operations.

## Establish the task

Read applicable target-project instructions. Define the observable outcome, affected surfaces, and completion checks. Resolve routine local choices from established conventions.

Discover only unknown facts needed by those surfaces: architecture, namespaces, source paths, framework, dependencies, configuration, security, tests, and deployment. Reuse verified facts while their source remains unchanged.

Inspect the nearest implementation and its callers. Identify existing delivery surfaces affected by the behavior. Do not assume Backendbase names, API count, paths, schemas, credentials, hosts, or fixtures apply to the target.

Ask only for unresolved decisions that materially change public behavior, compatibility, required platform choices, shared data, or external effects. Continue independent work while a decision is pending.

## Select detailed guidance

Use a narrow artifact skill when it adds a required procedure or invariant not already covered. Its discovery and verification steps share the evidence collected for this task.

Read only needed sections of [the feature pattern](references/backendbase-pattern.md):

| Question | Reference sections |
| --- | --- |
| Where does the change belong? | Adaptation Map; Dependency Direction; Minimum Feature Slice |
| Which delivery surfaces and contracts change? | Affected-delivery pass; Boundary Rules |
| How are handlers and adapters reached? | CQRS Contract; Registration Checklist |
| How do persistence and events remain consistent? | Cross-layer implementation gates; Transactional Publication |
| Which example limitations or checks matter? | Relevant Known Source Limits to Correct entries; Verification Baseline |

When scope expands, inspect the new surface and reassess affected decisions. Do not restart unrelated discovery.

## Implementation constraints

- Follow target platform rules and applicable skill invariants. Report source conflicts before choosing a deviation.
- Add only layers required by the behavior. Keep domain and application code independent from frameworks and infrastructure.
- Name new contexts after the target business capability. Omit `Context` and `BoundedContext` suffixes and demonstration prefixes. Use domain terms for entities and operations.
- Validate untrusted input at its boundary. Keep business rules in domain behavior and vendor or database operations behind project-owned ports.
- Align fields, types, required state, defaults, errors, authorization, and versions across participating contracts. Update every affected existing delivery surface.
- Verify actual container, bus, route, provider, or registry reachability when registration changes. Direct unit tests alone do not prove wiring.
- Add integration events only when requested behavior requires them. Prove external producer-to-carrier mapping. Do not claim exactly-once delivery.
- Generate a migration only for authorized schema scope after mapping and repository behavior are established. Review every statement and exclude unrelated changes.
- Separate migration drafting from database application. Apply only with explicit authority for the identified database. Do not invent seed rows or schema fields.
- Use existing explicit authority for the same operation, target, and scope. Ask again when those details materially change. Code work alone does not authorize deployment or remote synchronization.

## Verify and finish

Select checks from changed behavior and target policy. Run focused regression tests and required static analysis, complexity, and style checks. Add architecture, API, database, messaging, or release checks when affected. Run the full suite when risk or project policy requires it.

Reuse a successful result across skills while its relevant inputs and environment remain unchanged. Repeat checks after relevant edits, failures, or new evidence. Do not run application checks for prose-only work.

For implementation requests, fix defects caused by the change and continue until the outcome and required checks are complete. Update affected contracts, examples, operations guidance, and documentation. Follow the target changelog policy.

Report the delivered behavior, applicable-rule compliance, verification results, and concrete blockers or unverified dependencies. A review gate is needed only for an unresolved decision or an operation outside existing authority.
