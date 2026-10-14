# OpenAPI and Bruno

OpenAPI defines the public contract. Bruno supplies executable examples against a running API.

## Ownership

- Edit OpenAPI source under `resources/api-docs`.
- Do not hand-edit generated `public/*/docs/*-merged.yml` documents.
- Regenerate the merged file after each source change.
- Keep route placeholders equal to OpenAPI path parameter names.
- Keep one unique operation ID per operation.
- Use shared headers and problem responses from `resources/api-docs/common/components.yaml`.

## Change rule

Review the route, middleware, controller, OpenAPI operation, Bruno request, and tests as one contract change.

Run:

```sh
composer run generate-example-api-spec
vendor/bin/php-openapi validate public/example-api/docs/example-api-merged.yml
bin/bruno example-api local
```

Bruno environment files must contain placeholders, not live secrets. Assertions must check the status and important response fields.

Identity realm sources, generated documents, and Bruno collections have matching operations.
Run `composer run generate-identity-api-specs` and `composer run validate-identity-api-specs` after realm contract changes.
Identity requests use strict input mapping. Contact ownership and TOTP enrollment are separate steps.
Bruno identity collections use blank credential and proof variables. Populate them only for an identified test target.

Example collection endpoints accept positive `pageSize` and `page` values. Their calculated offset must fit a signed 64-bit integer. Invalid offsets return 400 before query dispatch. Group results include the complete distinct-group total and the requested page.

Basis: `resources/docs/6-openapi-and-bruno.html`.

Identity API source specifications and Bruno collections cover the same operations as trusted realm routes.
Optional trace, timezone, and language headers use the common contract. Problem responses include a stable code.
Bruno scripts check response envelopes and retain challenge and token values only as runtime variables.
