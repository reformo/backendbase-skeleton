---
name: backendbase-add-integration-event-producer
description: Add a new versioned integration-event producer and transactional outbox write to an existing Backendbase-style messaging runtime. Do not use for domain events, consumers, or changes to a released event contract.
---

# Add an integration-event producer

Read [references/backendbase-pattern.md](references/backendbase-pattern.md) before editing.

## Workflow

1. Read applicable `AGENTS.md` files and discover the owning context, namespace, command handler, messaging contracts, transaction port, tests, and configured service identity.
2. Confirm that the requested fact must cross a process or service boundary.
3. Define a stable event name, explicit version, and typed JSON-compatible payload.
4. Create the complete event before entering the transaction.
5. Store the domain mutation and outbox row through the existing integration-event transaction.
6. Add schema, handler-order, and atomic rollback tests.

## Invariants

- Do not serialize aggregates or command objects.
- Do not publish directly from a command handler.
- Do not perform external input or output in the transaction callback.
- Keep the producer name, version, and payload stable after release.
- Do not copy Backendbase `Example_*` names or namespace values into the target.

## Completion report

Report event name, version, payload fields, transaction boundary, tests, consumer impact, and any unpublished compatibility assumption.
