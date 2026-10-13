# ADR-0014: Test Architecture and Mandatory Quality Gates

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The platform needs evidence for behavior, adapter contracts, dependency boundaries, and release tooling. Test ownership must follow module ownership.

[Testing guidance](../platform/17-testing.md) defines risk-based local checks. [Composer scripts](../../composer.json) define the full executable-line coverage gate.

The [final decision report](references/2026-10-13-identity-access-decisions.md#migration-measurement-and-verification) requires real MySQL 8.4 and Redis 8 evidence for the new target behavior. Memory and SQLite tests do not prove InnoDB locking or Redis authentication behavior.

- **Constraints:** Keep tests isolated and use provider doubles. Preserve PHPStan level 8, complexity at most 12, style, coverage, and architecture gates. Use identified prepared targets for service-backed checks.

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
- **Rationale:** Automated checks make platform rules observable. Verification must also use the real database and Redis where their transaction, mapping, locking, and failure semantics control behavior.

## Consequences

- **Positive:** Movable module tests remain with their module. Full-suite results include executable-line coverage and deployment fixtures.
- **Negative / Trade-offs:** Real-service checks add environment and recovery requirements. The [release workflow](../../.github/workflows/release-artifact.yml) still disables coverage while invoking the coverage-required test script. The new target checks are requirements, not completed evidence.

## Implementation & Validation

- **Rollout Steps:** Maintain tests by ownership and risk. Add isolated MySQL 8.4 and Redis 8 scenarios for the target changes. Preserve existing gates and resolve release coverage configuration.
- **Verification:** Check UUID round trips, virtual columns, collation, and schema differences. Use separate connections for concurrency races and deadlock recovery. Test realm rejection, revocation, Redis failure, deletion, and restoration. Confirm current-revision evidence for all existing gates.
- **Review Date:** Review when test isolation, coverage scope, or release gates change.
