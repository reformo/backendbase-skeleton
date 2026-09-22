---
name: backendbase-add-external-integration-event-subscriber
description: Add a versioned queue-delivered integration-event carrier and subscriber to an existing Backendbase messaging runtime, including registry and inbox behavior tests. Do not use for internal subscribers or generic queue payloads.
---

# Add an external integration-event subscriber

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and discover the consuming context, namespace, source service, event registry, carrier mapper, inbox, service provider, and tests.
2. Obtain the exact producer event name, version, and serialized payload.
3. Create a typed versioned carrier that matches that payload exactly.
4. Add a narrow external subscriber and register event, subscriber, carrier, and version.
5. Keep database mutations inside the inbox transaction. Use the external-effect protocol for provider calls.
6. Classify failures as permanent, retryable before an effect, or unknown after an effect starts.
7. Add producer-to-carrier, duplicate, failure-classification, and effect-safety tests.
8. Deploy new consumer versions before producer versions and retain old carriers until queues and dead-letter storage expire.

## Invariants

- Append the target runtime's consumer suffix only once.
- Unknown versions, missing metadata, wrong subscriber types, and mapping errors are permanent failures.
- Provider calls need a durable unique effect claim, a stable provider idempotency key when supported, and an explicit reconciliation path.
- Never retry an external call automatically after its outcome becomes unknown.
- Do not copy the known mismatched Backendbase version-one example payload.

## Completion report

Report source event identity, carrier schema, subscriber effect type, registry key, inbox or external-effect behavior, reconciliation path, tests, and rollout dependencies.
