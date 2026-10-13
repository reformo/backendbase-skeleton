# ADR-0005: PHP and Lightweight HTTP Framework Stack

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

The backend needs HTTP routing, standard messages, and dependency composition. These tools must support the platform dependency rules.

The [platform stack](../platform/00-platform.md) and [Composer manifest](../../composer.json) define PHP 8.5, Slim 4, Laminas Diactoros, and PHP-DI.

- **Constraints:** Use supported project runtime versions. Keep framework APIs inside infrastructure and composition code.

## Considered Options

### Option A: PHP with focused HTTP and container libraries

- **Pros:** The platform selects each infrastructure capability explicitly. Business modules remain independent of the HTTP framework.
- **Cons / Risks:** The project owns integration, configuration, and upgrade coordination between libraries.
- **Estimated Cost / Effort:** Medium

### Option B: Integrated full application framework

- **Pros:** One framework supplies more conventions and tooling.
- **Cons / Risks:** Migration affects bootstrap and adapters. Framework conventions can enter business code without strict boundaries.
- **Estimated Cost / Effort:** High

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** The current stack provides the required HTTP and composition functions while supporting existing ports and dependency checks.

## Consequences

- **Positive:** HTTP infrastructure remains small and replaceable through adapters.
- **Negative / Trade-offs:** Library compatibility and lifecycle updates require project maintenance.

## Implementation & Validation

- **Rollout Steps:** Keep framework use in infrastructure. Review runtime and locked dependency changes together. Verify bootstrap before upgrading.
- **Verification:** Run HTTP, container, and architecture checks. Verify supported versions against [composer.json](../../composer.json) and its lock file.
- **Review Date:** Review when runtime support ends or required HTTP capabilities exceed the current stack.
