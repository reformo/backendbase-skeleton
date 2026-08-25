# Environment and Configuration

Environment variables supply deployment values. PHP configuration arrays define structure. PHP-DI exposes the merged settings.

## Precedence and loading

- `backendbaseEnv()` reads `$_ENV`, then the process environment, then a code default.
- An empty string is present and does not activate the default.
- Shared `config/autoload` providers load before the selected API providers.
- Process values are not replaced by local Dotenv values.
- Most values remain strings unless configuration casts them.

Use exact `BACKENDBASE_ENV` values: `dev`, `test`, `ci`, `stage`, or `production`.

The public API can cache merged configuration, the container, proxies, and routes. Clear cache after environment, route, or dependency changes:

```sh
bin/backendbase clear-cache
```

A cached public boot skips Dotenv. Restart long-running workers after configuration changes.

There is no central configuration schema. Many external-service settings fail only when PHP-DI resolves the lazy service.

Treat `.env.example` as a local aid, not an authoritative variable catalog. Keep database, broker, AWS, JWT, API, object-store, and Tolgee secrets outside source and logs.

Basis: `resources/docs/7-env-and-config.html`.
