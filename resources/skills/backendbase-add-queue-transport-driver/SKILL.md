---
name: backendbase-add-queue-transport-driver
description: Add a broker transport adapter to a Backendbase-style queue port, including envelope mapping, acknowledge-retry-reject semantics, configuration, dependency selection, readiness, and tests. Do not use for a new message processor.
---

# Add a queue transport driver

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and discover the queue port, current drivers, mapper, settings, container selector, readiness system, and transport tests.
2. Define publish durability, route resolution, normalized incoming envelope, and acknowledgment semantics.
3. Keep vendor SDK types inside the infrastructure adapter.
4. Add validated configuration, a focused dependency provider, and a bounded readiness check.
5. Test publish, receive, ACK, RETRY, REJECT, handler exceptions, malformed input, timeout, and resource cleanup.
6. Document broker-owned dead-letter and retention resources without creating them.

## Invariants

- Preserve one normalized message shape across transports.
- Make REJECT behavior explicit; it can differ from RETRY by broker.
- Use finite connection, read, write, and poll timeouts.
- Keep credentials out of source and logs.
- Record safe transport diagnostics before a caught handler exception leaves a message for retry.
- Do not provision queues or dead-letter resources without explicit authorization.

## Completion report

Report driver selection value, envelope mapping, delivery semantics, timeouts, readiness, infrastructure prerequisites, tests, and required live checks that could not run.
