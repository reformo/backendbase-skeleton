---
name: backendbase-add-openapi-operation
description: Add or change one OpenAPI operation in a Backendbase-style API and keep generated documentation aligned. Use for public contract work; do not use to implement the runtime endpoint or a Bruno-only test.
---

# Add an OpenAPI operation

## Outcome

Produce one valid public operation whose method, path, parameters, body, security, responses, and generated document match the intended runtime contract.

## Task-scoped discovery

Inspect only unknown facts needed by the affected behavior. Reuse applicable instructions and verified project facts while their sources remain unchanged.

1. Read applicable `AGENTS.md` files.
2. Discover the owning API from the target selector, route registry, editable OpenAPI roots, generated outputs, and Bruno collections. Do not assume a fixed API list.
3. Inspect Composer scripts, shared components, CORS configuration, tests, and the nearest operation.
4. Trace the matching runtime route, middleware, boundary validation, command or query, controller response, and errors.
5. Resolve the operation ID, security rule, required fields, compatibility requirement, and executable example coverage.
6. Read only the sections of [references/backendbase-pattern.md](references/backendbase-pattern.md) needed to resolve this task.

Do not invent a new route, schema, public policy, or compatibility break without user intent.
If runtime implementation is outside scope, deliver the contract draft and report that final reconciliation remains pending. Do not implement the endpoint from this contract-only skill.

## Target-project adaptation

Use the target API version, source layout, servers, media types, component names, security schemes, generation command, and compatibility policy. Do not copy Backendbase hosts, headers, example credentials, resource fields, or fixture values unless the target uses them.

## Workflow

1. Draft the intended method, path, inputs, security, success response, headers, and errors in the authoritative OpenAPI source.
2. Treat that definition as a draft until a complete application path and runtime endpoint exist.
3. Reconcile the draft against the route, middleware, sanitizer, validator, command or query construction, response mapper, and tests.
4. Reuse shared parameters and problem responses only when their contracts match.
5. Define exact path, query, header, body, success, and failure shapes, including required state, nullability, defaults, formats, ranges, and limits.
6. Express anonymous, API-key, bearer, ACL, AND, and OR semantics correctly.
7. Verify each documented request header is enforced as intended at runtime. When CORS is configured, verify that it permits the header.
8. Update the matching Bruno request and assertions when executable coverage exists.
9. Regenerate the merged document with the project command.
10. Validate source and generated documents, review the generated diff, then perform a semantic runtime audit.

## Backendbase invariants

- Editable sources live under `resources/api-docs`.
- Generated merged YAML is never hand-edited.
- Each operation ID is unique.
- Route placeholders and OpenAPI path parameter names are identical.
- In an unmodified Backendbase API, each operation references `Accept-Language`, `The-Timezone-IANA`, `X-Request-Id`, and `X-Source-Id`.
- Shared problem responses come from the common components file when they match.
- Two schemes in one security requirement are logical AND. Separate requirements are logical OR.
- An anonymous operation declares `security: []`; an API-key-only operation is not public.
- Required request fields and response fields match runtime behavior.
- OpenAPI validation does not replace a semantic comparison with runtime and tests. Include maintained Bruno coverage and configured CORS when applicable.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

```sh
composer run generate-{api-slug}-spec
vendor/bin/php-openapi validate resources/api-docs/{api-slug}/{root-spec}.yml
vendor/bin/php-openapi validate public/{api-slug}/docs/{api-slug}-merged.yml
git diff -- public/{api-slug}/docs/{api-slug}-merged.yml
```

Run focused API tests when runtime alignment is part of the change.

## Completion report

Report the operation ID, source files, security semantics, generation result, validation result, runtime differences found, and blocked required checks.
