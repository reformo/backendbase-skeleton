# ADR-0014: Test Architecture and Mandatory Quality Gates

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The platform needs evidence for behavior, adapter contracts, dependency boundaries, and release tooling. Test ownership must follow module ownership.

[Testing guidance](../platform/17-testing.md) defines risk-based local checks. [Composer scripts](../../composer.json) define the full executable-line coverage gate.

- **Constraints:** Keep tests isolated. Use provider doubles. Enforce PHPStan level 8, complexity at most 12, coding style, and architecture boundaries for code changes.

## Considered Options

### Option A: Layered tests with behavior contracts and mandatory gates

- **Pros:** Different checks cover behavior and structure. Common repository contracts compare adapters against the same port.
- **Cons / Risks:** Strict coverage and multiple gates add maintenance. Passing line coverage does not prove every behavior.
- **Estimated Cost / Effort:** Medium

### Option B: Example tests and manual release review

- **Pros:** Initial verification infrastructure is smaller.
- **Cons / Risks:** Regressions and dependency violations rely more on individual review.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Automated boundary and behavior checks make platform rules observable. Local checks remain proportionate to the changed behavior.

## Consequences

- **Positive:** Movable module tests remain with their module. Full-suite results include executable-line coverage and deployment fixtures.
- **Negative / Trade-offs:** The [release workflow](../../.github/workflows/release-artifact.yml) currently disables coverage while invoking the coverage-required test script. This gate needs alignment.

## Implementation & Validation

- **Rollout Steps:** Maintain tests by ownership. Select local checks by risk. Preserve full continuous-integration and release gates. Resolve coverage configuration before relying on release execution.
- **Verification:** Verify behavior assertions, architecture checks, PHP analysis, style, complexity, documentation links, and full-suite coverage. Confirm evidence applies to the current revision.
- **Review Date:** Review when test isolation, coverage scope, or release gates change.
