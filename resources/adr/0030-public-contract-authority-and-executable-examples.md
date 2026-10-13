# ADR-0030: Public Contract Authority and Executable Examples

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Clients need one declared HTTP contract. Generated documents and executable examples must remain consistent with routes, middleware, controller behavior, and errors.

[API contract guidance](../platform/08-api-contracts.md) makes authored OpenAPI authoritative and Bruno the executable example format.

- **Constraints:** Keep operation identifiers unique. Match path parameter names. Exclude live secrets from examples. Review runtime and documentation together.

## Considered Options

### Option A: Authored OpenAPI with generated output and aligned Bruno examples

- **Pros:** Public behavior is explicit and reviewable. Executable examples check important statuses and fields.
- **Cons / Risks:** Contract sources, runtime code, and examples need synchronized changes.
- **Estimated Cost / Effort:** Medium

### Option B: Generate all contracts from controllers

- **Pros:** Some route and schema changes can follow code automatically.
- **Cons / Risks:** Framework annotations can become the public contract source. Examples and middleware policy still need separate verification.
- **Estimated Cost / Effort:** Medium

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** A reviewed public contract should express intended behavior explicitly. Generated output remains a build product of that source.

## Consequences

- **Positive:** Shared [OpenAPI components](../api-docs/common/components.yaml) provide common headers and problem responses.
- **Negative / Trade-offs:** Successful schema validation does not prove runtime alignment. Existing header and body alignment risks need boundary checks.

## Implementation & Validation

- **Rollout Steps:** Edit OpenAPI sources. Update routes, middleware, responses, and Bruno requests together. Regenerate served documents.
- **Verification:** Validate source and generated OpenAPI. Review the generated diff. Run aligned boundary tests and Bruno requests against an identified prepared target.
- **Review Date:** Review when the public API format, versioning policy, or contract-generation model changes.
