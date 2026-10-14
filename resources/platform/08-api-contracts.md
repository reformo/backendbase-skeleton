# OpenAPI and Bruno

OpenAPI defines the public contract. Bruno supplies executable examples against a running API.

## Ownership

- Edit OpenAPI source under `resources/api-docs`.
- Do not hand-edit generated `public/*/docs/*-merged.yml` documents.
- Regenerate the merged file after each source change.
- Keep route placeholders equal to OpenAPI path parameter names.
- Keep one unique operation ID per operation.
- Group every operation with a non-empty `tags` list, including operations in referenced path files.
- Declare each assigned tag in the root document's `tags` section with a description.
- Use the same tag name for the same endpoint group across APIs.
- Use shared headers and problem responses from `resources/api-docs/common/components.yaml`.

## Response status and bodies

Successful JSON bodies use descriptive resource or result keys without generic `data` wrappers or top-level `statusCode` fields.
The HTTP response carries the status code. Keep existing resource fields and their nested values.
Use singular resource keys, plural collection keys, and `camelCase`. Select the key in the HTTP adapter.
`ActionPayload` serializes the supplied body directly, including named object values and empty collections.
Preserve direct Example projections, health bodies, and empty responses where those shapes are documented.
Problem Details errors retain their integer `status` field and `application/problem+json` media type.
The error body status must equal the HTTP response status, including missing routes and unsupported methods.
Slim exceptions use standard HTTP titles: 404 returns `Not Found`, and 405 returns `Method Not Allowed`.
Domain errors retain their mapped problem titles. Unexpected failures return `Internal Server Error` with status 500.
Bruno checks the HTTP status, the documented response key, resource fields, and absence of top-level `data` and `statusCode`.

## Identity response keys

| Endpoint or result | Response key |
| --- | --- |
| UserApi current identity | `user` |
| SystemAdminApi current identity | `admin` |
| User registration | `registration` |
| Contact ownership verification | `ownershipVerification` |
| Contact proof resend and password authentication start | `challenge` |
| TOTP enrollment preparation and completion | `enrollment` |
| Authentication completion and session termination | `session` |
| Access token revocation | `token` |
| Account closure | `account` |
| Admin invitation | `invitation` |
| Admin approval | `admin` |
| Actor change and actor history | `actor` |

Completion responses keep the existing `completed` field inside the named result.
Choose response names from the public resource or result. Do not expose internal command or class names as wrapper keys.
Keep Problem Details fields at the root without a resource wrapper.

## Change rule

Review the route, middleware, controller, OpenAPI operation, Bruno request, and tests as one contract change.

Run:

```sh
composer run generate-example-api-spec
composer run validate-example-api-spec
bin/bruno example-api local
```

Bruno environment files must contain placeholders, not live secrets. Assertions must check the status and important response fields.

Identity realm sources, generated documents, and Bruno collections have matching operations.
Run `composer run generate-identity-api-specs` and `composer run validate-identity-api-specs` after realm contract changes.

The grouped commands call the individual API commands below.

| API | Generate | Validate source and merged document | Generated document |
| --- | --- | --- | --- |
| UserApi | `composer run generate-user-api-spec` | `composer run validate-user-api-spec` | `public/user-api/docs/user-api-merged.yml` |
| SystemAdminApi | `composer run generate-system-admin-api-spec` | `composer run validate-system-admin-api-spec` | `public/system-admin-api/docs/system-admin-api-merged.yml` |

Each API documentation viewer loads the merged YAML from its own `public/{api-slug}/docs` directory.
Local housekeeping generates ExampleApi, UserApi, and SystemAdminApi documents.
Quality and release workflows use the grouped identity commands.

## Endpoint groups

| Endpoint group | Tag |
| --- | --- |
| `/auth/*` | `Authentication` |
| `/identity` and `/identity/*` | `Identity` |
| `/administration/*` | `Administration` |
| `/`, `/_status`, `/_status/ready` | `System` |
| `/example-types/*` | `Example` |

Each API validation command runs schema validation and `bin/check-openapi-tags.php` for source and merged documents.
The tag check resolves referenced path files. It rejects missing or empty operation tags, undeclared tags, duplicate declarations, and blank or padded names.
Add a new declared tag when a new endpoint group needs one.

Identity requests use strict input mapping. Contact ownership and TOTP enrollment are separate steps.
Bruno identity collections use blank credential and proof variables. Populate them only for an identified test target.

Example collection endpoints accept positive `pageSize` and `page` values. Their calculated offset must fit a signed 64-bit integer. Invalid offsets return 400 before query dispatch. Group results include the complete distinct-group total and the requested page.

Basis: `resources/docs/6-openapi-and-bruno.html`.

Identity API source specifications and Bruno collections cover the same operations as trusted realm routes.
Optional trace, timezone, and language headers use the common contract. Problem responses include a stable code.
Bruno scripts check response envelopes and retain challenge and token values only as runtime variables.
