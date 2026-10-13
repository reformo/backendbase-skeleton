# ADR-0035: Local Translation Catalogs and Controlled Synchronization

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

HTTP translation should not depend on a remote translation service. Catalog changes and external synchronization need separate control over their write effects.

[Internationalization guidance](../platform/21-i18n.md) uses local PHP dictionaries and Tolgee only for source synchronization.

- **Constraints:** Keep stable keys and matching placeholders. Protect synchronization credentials. Authorize the direction, project, namespace, locales, and create-or-replace effects.

## Considered Options

### Option A: Local runtime catalogs with explicit synchronization tools

- **Pros:** Requests do not depend on Tolgee or Redis for translation. Catalog content is reviewable with code.
- **Cons / Risks:** Exports can replace local files. Locale content and synchronization conflicts need review.
- **Estimated Cost / Effort:** Medium

### Option B: Fetch translations from a remote service during requests

- **Pros:** Catalog updates can become available without a code release.
- **Cons / Risks:** Remote availability, latency, caching, and inconsistent catalog versions affect requests.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Local catalogs keep runtime translation independent of remote services. Explicit synchronization preserves control over local and external writes.

## Consequences

- **Positive:** The first Accept-Language value selects a supported exact locale. Missing or unsupported values use tr-TR.
- **Negative / Trade-offs:** Missing keys return the locale-qualified key without per-key fallback. The current English dictionary loads Turkish values.

## Implementation & Validation

- **Rollout Steps:** Maintain local catalogs and equal placeholder names. Review synchronization scope before execution. Permit only one file-writing synchronization process at a time.
- **Verification:** Test locale selection, quality-weight removal, default selection, missing keys, and placeholder consistency. Review exported catalog values and language accuracy.
- **Review Date:** Review when locale negotiation, catalog storage, fallback rules, or synchronization ownership changes.
