# Agent guidance comparison

Date: 22 September 2026.

The revised guidance reduced recorded file reads from 122 to 87 across five tasks. Both versions completed all five tasks.
The review classified 25 baseline reads as unrelated to their tasks. The revised run had none in that category.
Unnecessary questions, repeated successful checks, and incomplete tasks were zero in both runs.

This is one paired pilot on small, isolated projects. It does not establish a general performance improvement.
The [measurement data](agent-guidance-comparison.json) includes requests, event logs, acceptance results, and relevance decisions.

## Applied changes

Eric Provencher recommends precise skill triggers, selective reference loading, contextual repository guidance, clear decision boundaries, and explicit completion criteria.
These changes apply that advice to repository-owned guidance. Third-party skills are excluded.
See [Rethinking skills and prompts for GPT-6 Astra](https://developers.openai.com/blog/rethinking-skills-and-prompts-for-gpt-6-astra).

| Surface | Result |
| --- | --- |
| [Root guidance](../../AGENTS.md) | Resolve routine local choices. Ask about material unresolved decisions. Preserve authorization for the same action, target, and scope. |
| [Platform router](../platform/README.md) | Select context by affected behavior. Remove mandatory architecture and repository-map reads for every edit. |
| [Feature skill](../skills/backendbase-implement-feature/SKILL.md) | Use for coordinated changes across layers. Load reference sections when needed. Define outcomes instead of a fixed implementation sequence. |
| [Verification skill](../skills/backendbase-verify-change/SKILL.md) | Use for requested or necessary integrated reviews. Missing evidence matters; an unused skill is not itself a defect. |
| [Testing guidance](../platform/17-testing.md) | Select checks by changed behavior. Reuse successful checks while relevant inputs and the environment remain unchanged. |
| [Feature workflow](../platform/24-feature-workflow.md) | Continue through authorized implementation, checks, corrections, and documentation. |

The related skill references, project guide, testing guide, and changelog were synchronized.
Engineering policies remain in force. Object Calisthenics, Coding Style, and Language sections are unchanged.
PHPStan level 8, complexity 12, applicable style checks, and existing continuous-integration and release gates remain required.

The two skill descriptions decreased from 703 to 315 characters combined.
The four primary files decreased from 4,285 to 4,254 words combined.
Root guidance grew from 1,636 to 1,836 words to make authorization and completion explicit.
The observed benefit comes from selective loading, not a large reduction in total instruction length.

## Task results

Each count shows **before → after**.

| Task | Unnecessary questions | Irrelevant file reads | Repeated successful checks | Incomplete tasks | Acceptance |
| --- | ---: | ---: | ---: | ---: | --- |
| Exact typo correction | 0 → 0 | 6 → 0 | 0 → 0 | 0 → 0 | Both passed |
| Supplied translation edit | 0 → 0 | 7 → 0 | 0 → 0 | 0 → 0 | Both passed |
| Endpoint validation fix | 0 → 0 | 2 → 0 | 0 → 0 | 0 → 0 | Both passed |
| Migration draft | 0 → 0 | 4 → 0 | 0 → 0 | 0 → 0 | Both passed |
| Authorized synchronization | 0 → 0 | 6 → 0 | 0 → 0 | 0 → 0 | Both passed |
| **Total** | **0 → 0** | **25 → 0** | **0 → 0** | **0 → 0** | **5/5 → 5/5** |

| Task | File-read events | Distinct files read | Check events |
| --- | ---: | ---: | ---: |
| Exact typo correction | 11 → 5 | 10 → 5 | 2 → 1 |
| Supplied translation edit | 28 → 19 | 26 → 19 | 7 → 6 |
| Endpoint validation fix | 32 → 23 | 32 → 23 | 12 → 9 |
| Migration draft | 27 → 21 | 27 → 21 | 7 → 7 |
| Authorized synchronization | 24 → 19 | 24 → 18 | 9 → 8 |
| **Total across cases** | **122 → 87** | **119 → 86** | **37 → 31** |

Distinct-file totals are the sum of each case. A shared guidance file can count once in each project.
The baseline recorded 122 full-file reads. The revised run recorded 79 full-file reads and eight partial reads.
Check events can contain several related commands. Their totals are not counts of unnecessary checks.

## Correctness and boundaries

- **Typo:** Exact byte comparisons confirmed that only the requested word changed.
- **Translation:** Both messages matched the supplied text. Catalog keys, placeholders, runtime replacement, existing messages, and supported locales passed verification. Neither run synchronized data.
- **Endpoint:** Both runs recorded a failing regression before the fix. Independent probes covered malformed values, types, limits, defaults, empty results, and reader isolation. Restoring the original defect made both retained regression suites fail.
- **Migration:** Both runs created the requested nullable column draft, reverse SQL, and review notes. Notes covered existing rows, compatibility, lock risk, and recovery data loss. Print-only review succeeded. Captured schemas and migration ledgers stayed unchanged.
- **Synchronization:** Both runs recorded one authorized application of the existing command. Final stores contained exactly the missing scoped key. Existing values, other namespaces, other projects, and local dictionaries stayed unchanged.

Both endpoint runs corrected an introduced test style warning. Their checks then passed.
Those reruns followed relevant edits and count as necessary verification.
The final synchronization preview followed a data change and also counts as necessary verification.

The baseline handled existing authorization and draft-only scope correctly.
This pilot provides no evidence of improvement in questions, repeated checks, or completion.

## Measurement method

The baseline was frozen from commit `ec44e02bd507e071ae6b050ce20bae8c02c233b1`.
The revised guidance was frozen before either executor started. The measurements describe that frozen revision, not later instruction edits. Guidance files remained unchanged in all ten project copies.
Both conditions received identical requests, fixture sources, contracts, tests, tools, and event-recording instructions.

One independent executor handled each condition. Each completed the five cases in the same order.
Each executor started without the audit conclusions or the other condition. Context could carry between its five cases.
The agents inherited the parent model settings without an override. Local configuration selected GPT-6 Astra.
The subagent interface did not provide a separate backend model receipt.

The fixtures use different project names and plain PHP. This tests adaptation without Backendbase runtime dependencies.
Existing installed PHPStan and PHPCS tools provided actual quality checks.
All 20 initial fixture checks passed: five test runs, five PHPStan runs, five complexity runs, and five style runs.
The fixture style configuration uses PSR-12. It does not reproduce every Backendbase Doctrine style rule.
Migration review only printed SQL. Synchronization used a local JSON store.

| Metric | Definition |
| --- | --- |
| Unnecessary question | Requests information or authorization already supplied. Executors recorded proposed questions instead of interrupting a real user. |
| Irrelevant file read | Loads guidance without a task-specific purpose. Includes mandatory unrelated reading imposed by the older instructions. |
| Repeated successful check | Repeats the same successful check without changed relevant inputs or a new uncertainty. Distinct checks with overlapping coverage are excluded. |
| Incomplete task | Leaves a required artifact, authorized action, or required verification unfinished. Operations outside the request are excluded. |

Each file in a batch counts separately. Searches do not count as full-file reads.
Task manifests, logging instructions, implicit file access inside tools, and evaluator work are excluded.
Repeated reads remain in the totals. Two baseline translation rereads recovered truncated output.
The extra revised synchronization read inspected the changed store.

Relevance decisions are conservative. Architecture and integrated verification guidance remain relevant to the endpoint case.
Migration review guidance remains relevant to the draft case. The data file lists every read classified as unrelated.
This classification is a review judgment, not a token measurement.

Counters rely on executor event logs. Final artifacts received independent acceptance checks and scope review.
The scorer did not have a complete independent tool transcript. Logged reads and command execution are not independently proven by artifact checks.
No token, cost, or timing improvement is inferred.

These cases test guidance selection and local boundaries. They do not cover every feature-skill branch, live databases, deployments, or real translation providers.
The result supports retaining selective loading. It does not justify relaxing engineering checks or external-action boundaries.
