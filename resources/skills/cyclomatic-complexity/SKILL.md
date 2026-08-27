---
name: cyclomatic-complexity
description: Measure and reduce cyclomatic complexity in code that needs simpler control flow, clearer maintenance, or a pre-merge quality review. Use for requests about complexity, readability, nested logic, large functions, or spaghetti code. Do not use to change public APIs or behavior without explicit approval.
---

# Reduce Cyclomatic Complexity

## Outcome

Keep each touched function readable and behaviorally unchanged. Reduce unnecessary branching without hiding it in dense expressions or unrequested abstractions.

## Discover the Target Project

1. Read the applicable agent instructions and the project's quality configuration.
2. Inspect the touched functions, their callers, and their focused tests.
3. Use a configured complexity threshold when one exists.
4. Use a language-specific complexity tool when the project provides one.
5. Measure each touched function manually when no reliable tool is available.

Cyclomatic complexity equals one plus the number of independent decision points. Count conditional branches, loop branches, `case` branches, `catch` branches, ternary expressions, and boolean short-circuit branches according to the selected tool.

Use these thresholds only when the project defines no threshold:

| Complexity | Action |
| --- | --- |
| 1-5 | Leave the function unchanged unless another requested change needs it. |
| 6-10 | Refactor when the function is already in scope. |
| 11-15 | Refactor the function. |
| 16 or more | Split the function before delivery. |

For PHP, prefer a configured analyser. The current Backendbase reference project has no configured complexity command. Measure the affected functions manually or use the installed `sebastian/complexity` library outside the working tree. Do not add a dependency or a permanent script only to calculate one report.

## Refactor in This Order

1. Add guard clauses and return early to reduce nesting.
2. Extract one coherent branch into a small function with an intention-revealing name.
3. Replace a stable condition chain with a lookup table when it makes the mapping clearer.
4. Extract a complex condition into a named predicate.
5. Use polymorphism or a strategy only when type switching occurs in at least two places.
6. Extract a loop body and use `continue` to remove nested conditions.

Preserve the target project's public signatures unless the user approves a contract change. Keep business decisions in their existing architectural layer. Do not move complexity into a dense expression, an unclear helper, or a generic abstraction.

## Verify

1. Measure the touched functions before the refactor.
2. Run the smallest relevant test before changing behavior when practical.
3. Refactor one function at a time.
4. Re-measure the changed functions.
5. Run focused tests and the target project's static analysis and style checks.

For an unmodified Backendbase project, run the focused PHPUnit test first, then run `composer phpstan` and `composer cs-check`. Run broader tests when the affected code crosses module, persistence, messaging, or HTTP boundaries.

## Completion Report

End the refactor with this report:

```markdown
## Complexity report

| Function | Before | After |
| --- | ---: | ---: |
| `parseOrder` | 14 | 4 |

Extracted: `validateHeader`, `resolveDiscount`

Behavior verified: focused test, PHPStan, and code-style checks
```

State every skipped check and its blocker. Do not claim behavior verification without a successful check.
