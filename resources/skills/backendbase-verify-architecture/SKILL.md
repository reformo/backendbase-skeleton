---
name: backendbase-verify-architecture
description: Review and verify Backendbase domain purity, bounded-context isolation, adapter direction, framework imports, and Shared dependency direction. Use after module or dependency changes or for an architecture review; do not use to weaken rules to permit a feature or to prove runtime behavior.
---

# Verify Backendbase Architecture

## Outcome

Show that production dependencies still point toward domain rules and project-owned contracts, or report each concrete violation with its owner.

## Required discovery

1. Read the target project's `AGENTS.md` files.
2. Inspect Composer autoloading, namespace, layer and container layout, test roots, architecture-test helpers, composition roots, and the nearest boundary and registration tests.
3. Read the changed production files and resolve their declared PHP dependencies.
4. Read [references/backendbase-pattern.md](references/backendbase-pattern.md).
5. Distinguish an implementation violation from an explicitly requested architecture-policy change.

## Target-project adaptation

Adapt namespace roots, directory selectors, framework prefixes, composition roots, and allowed technology boundaries to the target project. Do not copy `Backendbase\`, current framework packages, or path exclusions without checking local code.

## Workflow

1. Map each changed class to domain, contracts, application, adapter, infrastructure, Shared, or composition-root ownership.
2. Run the existing architecture suite before adding a rule.
3. For each violation, identify the exact file, dependency, crossed boundary, and smallest corrective move.
4. Fix production direction when the rule is valid. Do not add an exclusion to hide the violation.
5. Add or change architecture tests only when the user changed the policy or a stable untested boundary is in scope.
6. When composition changed, run a separate runtime reachability test through the actual container, provider, bus, route map, or registry.
7. Re-run focused architecture tests, PHPStan level 8, and affected behavior tests.

## Backendbase invariants

- Domain core must not depend on Infrastructure or Application.
- Business layers must not depend on context adapters.
- One bounded context must not depend on another bounded context.
- Business layers must not import the configured framework families.
- Shared must not depend on Domain or Infrastructure.
- Current Backendbase policy permits `Psr\Log\LoggerInterface` as an application port. Confirm the target policy before allowing it elsewhere.
- Current command and query contracts can reference their same-context handlers through one positional `CQRSHandler` attribute. This exception does not permit domain-core or cross-context dependencies.
- Tests are excluded from production dependency scans.
- `ServiceProvider.php` is a composition root and is excluded from some business-layer checks.
- Static dependency tests do not prove runtime registration, dynamic lookup, or business behavior.

## Verification

Run the specific architecture test, then the full architecture directory and PHPStan. Run relevant behavior and composition tests separately because architecture checks do not prove behavior or reachability.

## Completion report

Report each checked boundary, violations and fixes, any policy change, commands run, and runtime risks not covered by static analysis.
