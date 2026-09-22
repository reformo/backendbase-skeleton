---
name: backendbase-install-transactional-messaging
description: Install a Backendbase-style transactional outbox, inbox, bounded consumer failure policy, relay, and maintenance foundation in a PHP project that does not already provide them. Do not use to add one event to an existing runtime.
---

# Install transactional messaging

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed for the affected behavior and its constraints.

## Workflow

1. Read applicable `AGENTS.md` files and map the target architecture, autoloading, database, transaction manager, queue port, container, migrations, console, and tests.
2. Confirm that an equivalent outbox or inbox runtime does not already exist.
3. Define project-owned contracts before Doctrine or broker adapters.
4. Add schema, atomic producer storage, relay claims, normalized publication, inbox deduplication, failure tracking, and finite maintenance commands.
5. Add database lifecycle tests before applying any migration.
6. Apply migrations or start external workers only with explicit authorization.

## Invariants

- Commit business state and the outbox row in one database transaction.
- Keep network, process, and filesystem work outside that transaction.
- Design for at-least-once publication and idempotent consumption.
- Preserve evidence for duplicates, failed deliveries, and unknown external-effect outcomes.
- Do not claim exactly-once delivery.

## Completion report

Report installed contracts, schema, delivery semantics, retry limits, commands, migrations created but not applied, verification, and remaining infrastructure work.
