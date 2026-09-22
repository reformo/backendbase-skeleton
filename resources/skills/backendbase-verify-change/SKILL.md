---
name: backendbase-verify-change
description: Review a cross-layer Backendbase-style change when requested or when its risk needs an integrated review. Do not use for routine single-artifact completion.
---

# Verify a Cross-Layer Change

Verify the requested behavior and applicable project rules with evidence. Review-only requests produce findings. An implementation request also authorizes correction of verified defects within its scope.

## Establish the review

Read applicable target-project instructions and inspect the relevant diff. Reuse discovery and verification already completed for unchanged inputs.

Identify affected behavior, public contracts, persisted data, external effects, and operations. Inspect the nearest implementation and tests. Resolve unknown target namespaces, paths, framework, dependencies, configuration, security, test ownership, and deployment facts only when relevant.

Select platform files and narrow skills for constraints not already covered. An unused skill is not itself a defect. Missing evidence or a violated invariant is a defect.

## Route by risk

Use [the verification pattern](references/backendbase-pattern.md) as a selective reference:

| Review question | Reference sections |
| --- | --- |
| Which rules and evidence apply? | Evidence Order; affected rows of Review Matrix |
| Did dependencies or runtime registration change? | Architecture Checks; Registration Checks |
| Can input, persistence, or external effects fail unsafely? | Trust-Boundary Checks; Persistence and Messaging Checks |
| Does HTTP behavior match its contract? | Endpoint verification audit |
| Is a migration or release compatible? | Release-manifest parity |
| Which local checks are available? | Backendbase CI and verification commands |

Consult only relevant Known Backendbase Drifts entries. Confirm them against current target source. Backendbase paths and examples are provenance, not target-project defaults.

Review data loss, authorization, secrets, external effects, and incompatible contracts first. Then check domain invariants, transaction boundaries, dependency direction, runtime reachability, tests, and documentation for affected surfaces.

Trace changed fields or state through participating boundaries, contracts, handlers, adapters, responses or events, and tests. Omit absent surfaces. Do not invent work for them.

Current source establishes behavior, but does not authorize a platform-rule exception. Report conflicts. Ask only when existing requirements and authority do not resolve the decision.

## Coordinate verification

Use the smallest checks that prove the changed behavior, plus the target project's required gates. Add composition tests when registration changes and architecture tests when boundaries change. Validate affected API specifications and review maintained executable examples.

Run required static analysis, complexity, and style checks for code changes. Run document checks for prose-only changes. Run the full suite for broad changes or when project policy requires it.

One successful check can satisfy several skills. Repeat it only after relevant edits, failures, environment changes, or new evidence. Fix change-caused failures when implementation is authorized. Report unrelated failures without expanding scope.

Verify service-backed targets before execution. Reuse existing explicit authority for the same operation, target, and scope. A code or review request alone does not authorize a shared-database migration, deployment, or remote write.

## Report findings or completion

Lead findings with severity, exact location, concrete failure, and required correction. Do not edit during a review-only request.

For implementation work, continue through authorized corrections and affected rechecks. Finish when the requested behavior, applicable invariants, required checks, and affected documentation are complete.

Report the behavior verified, observed check results, concrete blockers, and unverified operations. Do not claim success for an unrun check or stop for review while authorized work remains.
