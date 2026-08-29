---
name: backendbase-verify-change
description: Verify a Backendbase-style change across architecture, behavior, persistence, HTTP contracts, messaging, operations, tests, and documentation. Use for a cross-layer review, pre-delivery check, regression audit, or evidence-backed completion assessment. Do not implement fixes unless the user also asks for changes.
---

# Verify a Cross-Layer Change

Review the change against target-project rules first. Use Backendbase as a reference architecture, not as a source of names or configuration values.

## Establish the Review Scope

1. Read the target project's agent instructions.
2. Inspect the working-tree status and the complete relevant diff.
3. Identify changed behavior, public contracts, persisted data, external effects, and operational steps.
4. When application behavior changed, enumerate the target project's existing delivery APIs and identify every affected one. Do not assume an API exists from its name.
5. Inspect the nearest unchanged implementation and its tests.
6. Read [the Backendbase verification pattern](references/backendbase-pattern.md).

For a review-only request, do not edit files or mutate external systems. For an implementation request, keep fixes limited to verified findings.

## Build an Impact Map

Trace every changed value or state transition through:

`boundary -> typed input -> application message -> handler -> model/port -> adapter -> response/event -> schema -> tests -> operations`

Mark absent surfaces as not applicable. Do not invent work for them.

## Review in Risk Order

1. Check data loss, authorization, secrets, external effects, and incompatible contracts.
2. Check domain invariants and transaction boundaries.
3. Check dependency direction and bounded-context isolation.
4. Check HTTP, console, configuration, persistence, and messaging boundaries.
5. Audit each affected endpoint as one route, middleware, controller, OpenAPI, executable example, and test contract.
6. Check registration and runtime discovery with composition tests, not static inspection alone.
7. Check release-manifest parity when a migration or rollback policy changed.
8. Check tests, static analysis, style, generated contracts, and synchronized documentation.

Use the narrow `backendbase-*` verification or artifact skill when a finding needs detailed rules for one surface.

## Run Proportionate Verification

Start with the smallest relevant test. Expand only after it passes or when the change has broader risk.

- Run focused unit and adapter tests.
- Run affected contract and boundary tests.
- Run applicable composition and registration tests through the actual container, bus, provider, route map, or registry.
- Run architecture tests.
- Run PHPStan at the required level.
- Run the configured complexity check.
- Run the style check.
- Generate and validate affected API specifications.
- Review the generated contract diff and affected Bruno requests without running them against an unapproved target.
- Run the full suite for cross-cutting, persistence, messaging, or release changes.

Do not report a check as passed unless you ran it and saw a successful result. Record commands that could not run and the reason.

## Report Findings or Completion

For findings, lead with severity, exact location, concrete failure mode, and required correction. Avoid speculative findings.

When no defect remains, report:

- The behavior and contracts verified.
- The checks run and their results.
- Any unverified external dependency or manual operation.
- Any known source limitation that the change intentionally avoids.
