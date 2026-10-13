# ADR-0009: Separate Write Persistence and Read Projections

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Writes must preserve aggregate rules. Reads need efficient projections for each use case. One persistence model does not have to serve both purposes.

The [persistence contract](../platform/09-persistence.md) uses Doctrine Object-Relational Mapper (ORM) for writes and Database Abstraction Layer (DBAL) for reads.

- **Constraints:** Keep persistence behind context ports. Bind SQL input parameters. Return typed read models rather than ORM records or raw rows.

## Considered Options

### Option A: ORM write adapters and DBAL read projections

- **Pros:** Writes reconstruct domain state. Reads select the required fields and pagination directly.
- **Cons / Risks:** Write mappings and read SQL need coordinated schema updates. Two adapter paths need tests.
- **Estimated Cost / Effort:** Medium

### Option B: One ORM model for all reads and writes

- **Pros:** One mapped model reduces initial persistence code.
- **Cons / Risks:** Query requirements can enter aggregates. ORM object graphs can make projection cost less explicit.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Separate adapters support business invariants and explicit read cost while preserving common repository contracts.

## Consequences

- **Positive:** Query models can evolve without exposing persistence records. Pagination and ordering remain explicit.
- **Negative / Trade-offs:** Schema changes can affect both record mapping and projection SQL.

## Implementation & Validation

- **Rollout Steps:** Define separate write and read ports where behavior requires them. Validate stored rows before constructing read models.
- **Verification:** Run common repository contracts against memory and Doctrine adapters. Verify production metadata, parameter binding, ordering, pagination, and rollback.
- **Review Date:** Review when a read projection needs separate storage or asynchronous refresh.
