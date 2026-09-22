---
name: backendbase-add-messaging-operations
description: Add finite outbox relay, status, cleanup, scheduling, and operational health support around an existing Backendbase transactional messaging runtime. Do not use to install the messaging foundation or replay dead letters.
---

# Add messaging operations

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and inspect outbox, inbox, failure records, console registration, deployment model, logs, and maintenance tests.
2. Define bounded relay, status threshold, retention, cleanup limit, and exit behavior.
3. Add project-owned operation ports and database adapters only where they are missing.
4. Add finite console commands and focused tests.
5. Document schedule frequency, overlap prevention, alerts, and worker supervision.
6. Install schedules or change live message records only with explicit authorization.

## Invariants

- Cleanup deletes only terminal records older than the approved retention period.
- A status breach must produce a nonzero exit code.
- Relay and cleanup runs must not overlap.
- Do not delete deduplication evidence during ordinary recovery.
- Do not add automatic dead-letter replay without a separate approved design.

## Completion report

Report commands, limits, retention, exit conditions, schedules generated but not installed, tests, monitoring gaps, and operator actions.
