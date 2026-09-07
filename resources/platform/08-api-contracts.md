# OpenAPI and Bruno

OpenAPI defines the public contract. Bruno supplies executable examples against a running API.

## Ownership

- Edit OpenAPI source under `resources/api-docs`.
- Do not hand-edit `public/example-api/docs/example-api-merged.yml`.
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

Current alignment risks include body requirements and CORS headers.

Basis: `resources/docs/6-openapi-and-bruno.html`.
