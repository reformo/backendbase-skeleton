# ADR-0033: S3-Compatible Object Storage Contract

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Applications need object download links and upload operations without depending on vendor client types. Deployments can use an S3-compatible endpoint or Amazon S3.

[External-service guidance](../platform/19-external-services.md) defines BucketService and [S3Bucket](../../src/Backendbase/Infrastructure/Adapters/S3Bucket.php).

- **Constraints:** Keep vendor types inside infrastructure. Protect credentials. Define download expiry, upload permissions, timeouts, and supported endpoint behavior.

## Considered Options

### Option A: Project-owned bucket port with an S3-compatible adapter

- **Pros:** Callers use one storage contract. Configuration can select compatible endpoints.
- **Cons / Risks:** Endpoint compatibility and signing behavior need adapter checks. The port supports only its declared operations.
- **Estimated Cost / Effort:** Medium

### Option B: Use vendor storage clients directly in application code

- **Pros:** Vendor features are available without additional port design.
- **Cons / Risks:** Application code depends on vendor types. Replacement and isolated tests become harder.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The declared capability is download links, local-file uploads, and signed browser POST uploads. A narrow port preserves that boundary.

## Consequences

- **Positive:** Configured CDN URLs serve downloads. Without a CDN base, the adapter creates time-limited signed S3 requests.
- **Negative / Trade-offs:** Current limits include forced JPEG download content type, unenforced upload content type, and an unbounded multipart retry.

## Implementation & Validation

- **Rollout Steps:** Bind BucketService to the configured adapter. Validate endpoint and bucket settings. Review content-type and retry limits before broader storage use.
- **Verification:** Use doubles for URL selection, signing, permissions, and failure mapping. Verify content-type and retry behavior on isolated prepared targets when changed.
- **Review Date:** Review when storage operations, access policy, content types, or endpoint support change.
