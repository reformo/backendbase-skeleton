---
name: backendbase-evolve-integration-event-contract
description: Evolve a released Backendbase-style integration-event contract without breaking queued or stored messages, using explicit compatibility analysis and versioned producer and consumer carriers. Do not use for a brand-new event.
---

# Evolve an integration-event contract

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and locate every producer, carrier, registry entry, subscriber, outbox row shape, queue retention rule, and contract test.
2. Establish whether the current event name and payload version have been released.
3. Classify the proposed change as compatible or incompatible.
4. Preserve released shapes; add a new version for incompatible changes.
5. Keep old carriers and registrations while old messages can exist.
6. Test the actual producer payload against each registered carrier.

## Invariants

- Never rename a published event without a migration plan.
- Never silently change a released payload shape.
- Update producer version only when the matching consumer registration exists.
- Reject unknown versions explicitly.
- Do not purge queues, outbox rows, or old contracts without explicit operational authority.

## Completion report

Report compatibility classification, old and new versions, retention decision, registrations, mapping tests, rollout order, and any required operator action.
