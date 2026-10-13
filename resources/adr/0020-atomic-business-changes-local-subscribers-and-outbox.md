# ADR-0020: Atomic Business Changes, Local Subscribers, and Outbox Writes

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Business state and publication intent must not commit separately. Local subscriber writes must succeed or fail with the producer operation.

The [transactional outbox flow](../platform/11-messaging-outbox.md) uses [DoctrineIntegrationEventTransaction](../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationEventTransaction.php).

- **Constraints:** Use one database connection for producer writes, local subscriber writes, and the outbox. Keep external effects outside this transaction.

## Considered Options

### Option A: One transaction for business changes, local subscribers, and optional outbox

- **Pros:** Committed state includes its publication intent. Subscriber and outbox failures roll back database work.
- **Cons / Risks:** Local subscriber work extends the transaction. Independent external effects cannot join its atomic boundary.
- **Estimated Cost / Effort:** Medium

### Option B: Commit business changes and publish directly afterward

- **Pros:** The write transaction is smaller. No outbox relay is needed.
- **Cons / Risks:** A crash or broker failure can lose publication after a successful business commit.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The database provides the atomic boundary. Broker availability must not determine whether committed business facts have publication intent.

## Consequences

- **Positive:** Local subscribers run before commit for both delivery modes. Queue-enabled events append one outbox row.
- **Negative / Trade-offs:** Subscribers must share the connection. Network, filesystem, process, and direct broker effects remain outside the transaction.

## Implementation & Validation

- **Rollout Steps:** Return the complete event from the transaction callback. Dispatch local subscribers. Append the optional outbox row. Relay only committed rows.
- **Verification:** Test rollback after producer, subscriber, and outbox failures. Verify both delivery modes and no republication during external consumption.
- **Review Date:** Review when local subscribers require another database or an external effect.
