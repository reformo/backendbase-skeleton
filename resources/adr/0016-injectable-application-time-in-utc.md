# ADR-0016: Injectable Application Time in UTC

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Retries, leases, availability, and retention depend on the current time. Direct system-time reads make exact boundary tests unstable.

The clock uses Coordinated Universal Time (UTC).

The [clock binding](../../config/dependencies/time.php) supplies the project-owned Clock through [SystemClock](../../src/Backendbase/Infrastructure/Adapters/Time/SystemClock.php).

- **Constraints:** Use UTC for behavioral clock values. Pass an explicit instant into pure policies. Preserve explicit time-zone handling for presentation.

## Considered Options

### Option A: Inject Clock and use fixed clocks in tests

- **Pros:** Time-dependent behavior has reproducible tests. Policy calculations can use one explicit instant.
- **Cons / Risks:** Clock dependencies and fresh readings must be placed correctly around external work.
- **Estimated Cost / Effort:** Medium

### Option B: Read native current time within each operation

- **Pros:** No clock port or test clock is needed.
- **Cons / Risks:** Boundary tests depend on wall time. Process time zones can change results.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** A shared UTC clock makes claims, retries, and cleanup boundaries explicit. Fixed clocks remove waiting from behavioral tests.

## Consequences

- **Positive:** Exact cutoff and lease tests are deterministic.
- **Negative / Trade-offs:** The date helper still exists for audit and explicit-zone use. Behavioral code must not bypass the clock.

## Implementation & Validation

- **Rollout Steps:** Inject Clock when time controls behavior. Pass instants to pure policies. Read completion time after external operations finish.
- **Verification:** Test before, equal-to, and after-cutoff behavior. Verify the production binding returns UTC regardless of process time zone.
- **Review Date:** Review when scheduling, time-zone policy, or distributed clock assumptions change.
