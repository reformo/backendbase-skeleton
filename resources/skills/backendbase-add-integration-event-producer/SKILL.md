---
name: backendbase-add-integration-event-producer
description: Add a new versioned integration-event producer and transactional outbox write to an existing Backendbase-style messaging runtime. Do not use for domain events, consumers, or changes to a released event contract.
---

# Add an integration-event producer

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and discover the owning context, namespace, command handler, messaging contracts, transaction port, tests, and configured service identity.
2. Confirm that the requested fact must cross a process or service boundary.
3. Define a stable event name, explicit version, and typed JSON-compatible payload.
4. Run authoritative database reads and mutations inside the integration-event transaction callback.
5. Return the complete event from the callback after required identifiers and state are known.
6. Add schema, handler-order, and atomic rollback tests.

## Invariants

- Do not serialize aggregates or command objects.
- Do not publish directly from a command handler.
- Do not perform external input or output in the transaction callback.
- Keep the producer name, version, and payload stable after release.
- Do not copy Backendbase `Example_*` names or namespace values into the target.

## Completion report

Report event name, version, payload fields, transaction boundary, tests, consumer impact, and any unpublished compatibility assumption.
