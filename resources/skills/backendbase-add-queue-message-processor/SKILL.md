---
name: backendbase-add-queue-message-processor
description: Add a Backendbase-style queue message processor that validates normalized input and returns acknowledge, retry, or reject outcomes, with separate database and external-effect safety modes. Do not use to implement a broker transport.
---

# Add a queue message processor

Read [references/backendbase-pattern.md](references/backendbase-pattern.md) before editing.

## Workflow

1. Read applicable `AGENTS.md` files and inspect the target queue envelope, outcome type, failure policy, inbox contracts, processor tests, and nearest application port.
2. Classify the work as a database mutation, an external effect, or pure computation.
3. Define required metadata and permanent versus transient failures.
4. Validate and map the message before invoking work.
5. Use the database inbox or external-effect inbox that matches the work.
6. Test success, duplicate, malformed input, active claims, unknown outcomes, and retry exhaustion as applicable.

## Invariants

- Return only the target runtime's explicit acknowledge, retry, or reject outcome.
- Never acknowledge before required durable work completes.
- Do not automatically retry an external effect with an unknown result.
- Do not log credentials, notification content, or unnecessary personal data.
- Do not mix broker acknowledgment code into application subscribers.

## Completion report

Report message metadata, processing mode, failure classification, idempotency key, outcomes, tests, and unresolved operator actions.
