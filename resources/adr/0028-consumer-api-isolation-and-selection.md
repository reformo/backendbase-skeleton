# ADR-0028: Consumer API Isolation and Selection

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Different consumers can need different HTTP routes, middleware, and public contracts. Transport-specific behavior must stay outside business modules.

[HTTP guidance](../platform/07-http-api.md) and [architecture rules](../platform/02-architecture.md) place consumer entry code under Infrastructure/Inbound.

The [final decision report](references/2026-10-13-identity-access-decisions.md#realm-authentication-and-sessions) adds separate USER and ADMIN inbound security policies. The report's route examples are conceptual; exact endpoints still require public contracts.

- **Constraints:** Select the API before composition. Keep inbound code and configuration separate. Select JWT realm policy from trusted route configuration before token validation.

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
- **Negative / Trade-offs:** The current [front controller](../../public/index.php) selects through X-Source-Id. Consumer selection does not authenticate an actor. Unverified token claims cannot select realm keys or policy.

## Implementation & Validation

- **Rollout Steps:** Preserve consumer API structure. Declare route modules and trusted [realm policies](0037-user-and-system-admin-jwt-realms.md). Keep authenticated principal construction within inbound middleware.
- **Verification:** Test API selectors and actual bootstrap policy. Reject cross-realm tokens and mismatched principals. Verify that unverified claims cannot alter the expected realm.
- **Review Date:** Review when a new consumer API or selector strategy is introduced.
