# ADR-0010: Typed Runtime Configuration

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Environment variables and configuration files are untrusted inputs. Invalid numeric values, provider settings, or missing fields must fail before use.

[Configuration guidance](../platform/15-configuration.md) and [settings registration](../../config/settings.php) define typed configuration at infrastructure boundaries.

- **Constraints:** Reject invalid values explicitly. Preserve documented defaults. Cache configuration consistently within a process.

## Considered Options

### Option A: Validated typed settings objects

- **Pros:** Adapters receive complete settings with known types. Validation stays at configuration boundaries.
- **Cons / Risks:** Invalid settings can surface when a dependency first resolves. Cached values require process restart after changes.
- **Estimated Cost / Effort:** Medium

### Option B: Read raw environment values in each adapter

- **Pros:** Adapters need less configuration mapping code.
- **Cons / Risks:** Parsing and defaults can differ. Invalid values can reach runtime operations.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Typed settings make provider and runtime assumptions explicit. Central parsing prevents silent numeric conversion and inconsistent defaults.

## Consequences

- **Positive:** Configuration errors identify the relevant setting. Internal code can use trusted values.
- **Negative / Trade-offs:** Dependency resolution does not validate every unused setting at startup. Deployment checks must cover enabled capabilities.

## Implementation & Validation

- **Rollout Steps:** Define typed settings for each infrastructure capability. Validate required fields and ranges. Restart workers after configuration changes.
- **Verification:** Test missing, invalid, boundary, and non-finite values. Resolve enabled production dependencies with representative configuration.
- **Review Date:** Review when a new provider, configuration source, or runtime refresh requirement is introduced.
