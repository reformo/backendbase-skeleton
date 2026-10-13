# ADR-0028: Consumer API Isolation and Selection

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Different consumers can need different HTTP routes, middleware, and public contracts. Transport-specific behavior must stay outside business modules.

[HTTP guidance](../platform/07-http-api.md) and [architecture rules](../platform/02-architecture.md) place consumer entry code under Infrastructure/Inbound.

- **Constraints:** Select the API before composition. Keep each API's entry code and configuration separate. Preserve module independence from HTTP.

## Considered Options

### Option A: Consumer-specific API directories with shared bootstrap

- **Pros:** Each consumer owns its transport contract. Shared infrastructure can provide common HTTP behavior.
- **Cons / Risks:** API selection and composition need consistent names. Shared bootstrap changes affect every consumer.
- **Estimated Cost / Effort:** Medium

### Option B: One combined route and middleware configuration

- **Pros:** Initial routing and startup configuration are smaller.
- **Cons / Risks:** Consumer policy can overlap. Contract ownership and middleware scope become harder to review.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Separate inbound surfaces preserve transport ownership while using the same application ports and bootstrap.

## Consequences

- **Positive:** Routes, middleware, controllers, configuration, OpenAPI, and Bruno can be maintained per consumer.
- **Negative / Trade-offs:** The shared [front controller](../../public/index.php) selects through X-Source-Id. An API-specific front controller alone does not replace this selector.

## Implementation & Validation

- **Rollout Steps:** Register each consumer target. Keep its inbound code and configuration together. Declare route modules explicitly.
- **Verification:** Test missing, invalid, and supported selectors. Verify each selected route and middleware set through the real bootstrap.
- **Review Date:** Review when a new consumer API or selector strategy is introduced.
