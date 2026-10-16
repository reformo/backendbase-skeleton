# OpenAPI and Bruno

OpenAPI defines the public contract. Bruno supplies executable examples against a running API.

## Ownership

- Edit OpenAPI source under `resources/api-docs`.
- Do not hand-edit generated `public/*/docs/*-merged.yml` documents.
- Regenerate the merged file after each source change.
- Group endpoint definitions in logical feature folders under each API source directory.
- Use lowercase `kebab-case` folder and file names.
- Store one Path Item Object per YAML file, with all methods for that path.
- Keep every root `paths` entry as a relative `$ref`, including metadata and health endpoints.
- Resolve each `$ref` from its containing file. Adjust shared and root component references when moving definitions.
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

## Example response keys

| Operation | Response shape |
| --- | --- |
| `GET /` | `apiMetadata` with `version`, `buildId`, and `cdnBaseUrl` |
| Example group list | `exampleGroups` with root `pageSize`, `page`, and `total` |
| Example list | `examples` with root `pageSize`, `page`, and `total` |
| Example detail | `example` |
| Example create, update, and delete | HTTP 204 with an empty body |
| Liveness | Root `status` |
| Readiness | Root `status` and object `checks`; HTTP 200 or 503 |

Example list items include `createdAt`. Detail timestamps use the OpenAPI `date-time` format.
Entry pages use ascending creation time, then UUID, before pagination in Doctrine and memory adapters.

Example types are `system` and `user`. Groups have 1 to 32 UTF-8 characters, keys have 1 to 160, and values have 0 to 2048.
`typeTargetId` is null or a positive signed 64-bit integer. It selects data independently of the example type.
POST and PATCH accept native JSON integers. Reads and DELETE accept integer query strings; omitted or empty query values select null.
Invalid input returns 400. Duplicate active identities return 409 on creation.

Example POST and PATCH require JSON objects within the 65536-byte request limit. A supplied non-null `details` value must be an object.
Top-level detail names cannot use canonical integer strings such as `"0"` or `"-1"`. Names such as `"01"` remain supported.
Member names cannot start with NUL at any depth. Request JSON decoding remains bounded to 64 levels.
Nested arrays and objects are permitted, including empty values.
Omitted or null creation details become an empty object. Omitted or null PATCH fields remain unchanged.
Accepted strings and JSON details remain unchanged in create, update, and read responses. Image-like names do not produce additional URL fields.
Clients that used inferred image URLs must construct them from their own explicit image contract.
Previously stored escaped text remains unchanged; this change does not rewrite stored data.
Empty Example `details` and readiness `checks` maps serialize as `{}`. Empty collections serialize as `[]`.
Example metadata uses `apiMetadata` instead of the previous `backendbase-api` key.
Clients that read the previous metadata key must update their lookup.

## Automated response checks

The specifications and Bruno collections contain 37 operations: ExampleApi has 9, UserApi has 11, and SystemAdminApi has 17.
`ApiDefinitionParityTest` compares source and merged operation definitions with Bruno methods and paths.
It also checks success key names and Bruno status, key, and wrapper assertions.
Identity API `ModuleRoutingTest` checks module registration, route names, and handler namespaces against the OpenAPI tags and operation IDs.
`ApiResponseAssertions` checks executed HTTP responses against resolved source and merged schemas.
It checks media types, required fields, allowed fields, object and array shapes, primitive types, enums, and empty bodies.
These shape checks do not validate every OpenAPI format or numeric limit.
HTTP tests cover identity flows, Example reads and writes, health responses, and failure paths.
Browser preflight and the Example catch-all are transport behavior outside the documented operation count.
Run live Bruno tests only against an identified, prepared target.

## Identity response keys

| Endpoint or result | Response key |
| --- | --- |
| UserApi current identity | `user` |
| SystemAdminApi current identity | `admin` |
| User registration | `registration` |
| Contact request, confirmation, and resend | `contactInfo` |
| Password login and session termination | `session` |
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

| Endpoint group | Source folder | Tag |
| --- | --- | --- |
| `/auth/*` | `authentication/` | `Authentication` |
| `/identity` and `/identity/*` | `identity/` | `Identity` |
| `/administration/*` | `administration/` | `Administration` |
| `/`, `/_status`, `/_status/ready` | `system/` | `System` |
| `/example-types/*` | `example/` | `Example` |

Each API root owns metadata, servers, tags, components, and the path reference index.
Endpoint files own operation definitions. Keep methods for one path together.
UserApi and SystemAdminApi controller modules use the same names as their OpenAPI tags.
Each module contains `ModuleConfig.php` and a `Handlers` directory with one handler per operation.
Register each module in `Controllers/ModuleRoutes.php`. Keep browser preflight in `Controllers/Root` because it has no OpenAPI operation.
For example, UserApi references `./authentication/login.yaml` from its root `paths` entry for `/auth/login`.
That file references shared headers through `../../common/components.yaml` and root schemas through `../user-api-openapi.yml#/components/schemas/...`.

Each API validation command runs schema validation and `bin/check-openapi-tags.php` for source and merged documents.
The tag check resolves referenced path files. It rejects missing or empty operation tags, undeclared tags, duplicate declarations, and blank or padded names.
Add a new declared tag when a new endpoint group needs one.

Identity requests use strict input mapping. Contact ownership and password authentication are separate steps.
Registration requires `email`, `mobile`, `password`, `emailContactInfoId`, `mobileContactInfoId`, and `verificationContextToken`.
Verify both contacts less than 12 hours before registration. Mobile uses E.164 format and is globally unique among active Accounts.
Login accepts `identifier` containing email or mobile, plus `password`. Username fields are rejected.
Contact request, confirmation, and resend use `contactInfo`. The former `/auth/ownership/*` paths are removed.
See [contact verification](operations/contact-verification.md) for public and authenticated Admin contracts.
Admin invitations require `accountId` and `evidence`. Current identity and actor history responses include Account `email` and `mobile`.
`POST /auth/login` returns `session` with `accessToken`, `tokenType`, and `expiresAt`.
TOTP enrollment and `/auth/login/complete` endpoints are removed. Update clients to use the single login response.
Bruno identity collections use blank credential and proof variables. Populate them only for an identified test target.

Example collection endpoints accept positive `pageSize` and `page` values. Their calculated offset must fit a signed 64-bit integer. Invalid offsets return 400 before query dispatch. Group results include the complete distinct-group total and the requested page.

Basis: `resources/docs/6-openapi-and-bruno.html`.

Identity API source specifications and Bruno collections cover the same operations as trusted realm routes.
Optional trace, timezone, and language headers use the common contract. Problem responses include a stable code.
Bruno scripts check response envelopes and retain contact IDs and authentication tokens only as runtime variables.

Account registration accepts optional nullable `firstName` and `lastName` fields.
Each supplied name has 1 to 160 UTF-8 characters without surrounding spaces or control characters.
Current identity and actor history include `firstName` and `lastName`, with null for omitted names.
