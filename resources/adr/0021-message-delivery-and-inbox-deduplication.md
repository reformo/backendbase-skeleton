# ADR-0021: Message Delivery Guarantees and Inbox Deduplication

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

A broker publication can succeed before the outbox records success. A relay or consumer can therefore receive the same message more than once.

The [outbox rules](../platform/11-messaging-outbox.md) and [consumer inbox rules](../platform/12-messaging-consumers.md) define the duplicate boundary.

- **Constraints:** Use a stable message identifier. Commit consumer database work and its processed marker together. Do not promise exactly-once external effects.

## Considered Options

### Option A: At-least-once delivery with transactional inbox deduplication

- **Pros:** Duplicate delivery does not repeat committed consumer database work while its inbox record remains.
- **Cons / Risks:** Delivery can repeat. Deduplication depends on identity, retention, and a shared transaction connection.
- **Estimated Cost / Effort:** Medium

### Option B: Broker acknowledgment without a durable consumer inbox

- **Pros:** Consumption requires less database state.
- **Cons / Risks:** A repeated delivery can repeat database changes. Broker acknowledgment cannot prove application atomicity.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The database transaction can join business work and the processed marker. Broker transport alone cannot provide that boundary.

## Consequences

- **Positive:** Processed duplicates acknowledge without redispatch. Consumer identity is the pair (consumer_name, message_id).
- **Negative / Trade-offs:** All matching subscribers under one consumer share the inbox transaction. Cleanup limits the deduplication period; external effects need separate controls.

## Implementation & Validation

- **Rollout Steps:** Preserve message identity through transport. Dispatch inside the inbox transaction. Commit the processed marker before acknowledgment.
- **Verification:** Test duplicates, rollback, and failure before acknowledgment through [DoctrineInboxMessageTransaction](../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineInboxMessageTransaction.php). Review [retention policy](0025-replay-and-deduplication-retention.md).
- **Review Date:** Review when consumer identity, retention, subscriber grouping, or external effects change.
