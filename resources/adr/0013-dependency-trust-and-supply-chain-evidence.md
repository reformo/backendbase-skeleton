# ADR-0013: Dependency Trust and Supply-Chain Evidence

- **Status:** Accepted
- **Date:** 2026-08-31
- **Owner / Deciders:** Mehmet Korkmaz <mehmet.korkmaz@reformo.net>
- **Ticket / PR Reference:** Not assigned.

## Context and Problem Statement

Locked dependency versions do not prove that downloaded package content is unchanged or safe. Build scripts must not execute unreviewed package code.

The [supply-chain tool](../../bin/composer-supply-chain.php) and [workflow](../../.github/workflows/composer-supply-chain.yml) maintain package digests and a Software Bill of Materials (SBOM).

- **Constraints:** Install reviewed dependencies in isolation. Disable plugins and scripts before content verification. Review lock and evidence changes together.

## Considered Options

### Option A: Locked dependencies with reviewed content evidence

- **Pros:** Content changes are detectable. Release manifests can bind dependency evidence to the artifact.
- **Cons / Risks:** Evidence must be regenerated from isolated installs and reviewed. Digests do not prove that content is safe.
- **Estimated Cost / Effort:** Medium

### Option B: Lock-file installation and advisory audit only

- **Pros:** Dependency maintenance needs fewer evidence files and review steps.
- **Cons / Risks:** Changed package contents can remain undetected. Known advisories cover only reported vulnerabilities.
- **Estimated Cost / Effort:** Low

## Decision Made

- **Chosen Option:** Option A.
- **Rationale:** Content verification complements version locking and advisory checks. The build verifies digests before enabling trusted dependency scripts.

## Consequences

- **Positive:** Dependency changes and artifact contents have reviewable evidence.
- **Negative / Trade-offs:** The process adds maintenance. Unknown vulnerabilities can remain in correctly matched package content.

## Implementation & Validation

- **Rollout Steps:** Install without plugins or scripts. Generate and review the SBOM and package digests. Verify evidence before trusted build steps.
- **Verification:** Run isolated content verification and advisory checks. Confirm release manifests bind the lock file and both evidence files.
- **Review Date:** Review when package sources, Composer behavior, or build trust boundaries change.
