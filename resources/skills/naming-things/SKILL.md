---
name: naming-things
description: Name or rename code identifiers, files, tests, and agent-facing artifacts. Use for naming requests while preserving project vocabulary and behavior.
---

# Naming Things

Use this skill when the user asks to name or rename code, files, classes, variables, functions, tests, prompts, tools, or other AI-facing project artifacts.

## Target discovery

Read the target project's instructions and naming rules. Inspect the affected source, configuration, contracts, and tests as needed.
Identify the target's namespaces, paths, framework-discovered names, external consumers, and verification commands before renaming.
Adapt the examples to the target project. Explicit project rules take precedence over this skill's examples.

## What this skill optimizes

Names are a shared interface between people, code, and agents. The compiler may accept two names, but a human or AI still has to recover meaning before it can search, explain, change, or review the result.

Optimize for four outcomes:

- **Find:** search and navigation land on the intended concept.
- **Understand:** the name supplies useful intent before the body is read.
- **Change:** a requested edit stays close to the right surface area.
- **Verify:** the target and its tests are easy to identify and check.

## Naming principles

- Inspect the surrounding system, module, file, class, function, and neighboring names before proposing a name. Readability is a relationship with an audience, not an isolated property.
- Use the shortest name that stays unambiguous. Let the context hierarchy do part of the work; do not repeat parent context that the caller can already see.
- Prefer purpose and intention over implementation details: `calculateTax` is stronger than `process`; `$validatedOrder` is stronger than `$data`.
- Use the domain vocabulary already shared by users and teammates. Use domain acronyms only when project rules permit them.
- Distinguish parallel concepts explicitly: use `$customerType` and `$productType`, not two vague `$type` values.
- Let names match how far they travel. A tiny local expression can be concise; a public or cross-module name must carry more context.
- Use nouns for things, verbs for commands, and noun-like names for queries when the caller's intent is clearer that way.
- If a block needs a comment to explain what it does, consider extracting it into a named function. A name turns prose into searchable, testable vocabulary.
- If a class is hard to name precisely, or its name needs “and” or “or,” inspect whether it has multiple reasons to change and should be split.
- Treat temporary names as temporary and searchable. Do not hide unfinished meaning behind `data`, `info`, `helper`, `misc`, or `doThing`.

Read [references/naming-framework.md](references/naming-framework.md) when comparing names or reviewing their context and purpose.

## The AI-assisted naming loop

Keep every naming decision close to this loop:

1. **Search** — find the concept using the repository's vocabulary.
2. **Read** — infer intent from the name, hierarchy, neighbors, and implementation.
3. **Edit** — make the smallest semantic change at the named surface.
4. **Test** — verify behavior and confirm the rename did not leave stale references.

Better names reduce how much code an agent must reconstruct from context. They also make the result easier for a reviewer to verify.

## PHP conventions

When PHP is involved, use PHP syntax in coding examples and apply these conventions unless the project explicitly overrides them:

- Classes and class-like types use `PascalCase`: `OrderValidator`, `TaxCalculator`.
- Variables use `camelCase` with the PHP sigil: `$orderTotal`, `$customerType`, `$trackingId`.
- Methods and functions use `camelCase`: `calculateTax()`, `firstItem()`, `processOrder()`.
- PHP class files normally match the class name: `TaxCalculator.php`.
- Preserve framework idioms when they are required for correctness or are already familiar to the team.

Before proposing a PHP name, check whether the identifier is a class, variable, method, namespace, filename, public API, or framework-discovered symbol. Public APIs, reflection-based names, and separately documented names may need extra caution before renaming.

Read [references/php-naming.md](references/php-naming.md) for PHP patterns and review prompts when naming PHP symbols.

## AI-facing surfaces

Treat filenames, class names, method and variable names, prompt names, tool names, folders, and test names as one connected vocabulary. Put distinctive intent where an agent can search it.

Prefer names such as:

- `CustomerPolicy.php` over `helper.php`.
- `summarizeContractWithRisks` over a generic prompt or tool label.
- `calculateTaxForOrder` over `doThing`.
- `tests/TaxCalculatorTest.php` over an unnamed verification file.

When drafting an AI change request, name the target, state the intent, preserve local conventions, and include verification. For example:

> Update `TaxCalculator::calculateTax()` to use `$taxableIncome`. Keep PascalCase classes, camelCase variables, and run the existing tests.

## Workflow for a naming request

1. Identify the concept, its purpose, its scope, its travel distance, and its audience.
2. Inspect the context hierarchy and nearby names; reuse established domain and framework vocabulary.
3. Ask five questions: What is the domain term? What is it for? How far does it travel? Is it unique here? Can it be renamed safely?
4. Propose the clearest concise name, with one alternative only when the tradeoff is meaningful.
5. Check for redundant context, collisions, buried meaning, inconsistent casing, and names that hide the useful word from search or completion.
6. If implementation was requested, use semantic rename tooling, update affected references, and run the narrowest relevant tests or checks. Do not use blunt search-and-replace for scoped identifiers.
7. Report the chosen name, why it fits, the affected surface, and any caveat such as public API or reflection usage.

## Boundaries

Keep refactoring within the requested scope. Preserve behavior and public contracts unless the user authorizes a change.
Do not turn naming advice into a reason to ignore correctness, usefulness, security, performance, testing, or deployment. Names support those outcomes; they do not replace them.
