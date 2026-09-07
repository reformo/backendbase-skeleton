# Environment and Configuration

Environment variables supply deployment values. PHP configuration arrays define the raw structure. PHP-DI exposes typed settings objects.

Configuration boundary objects validate the merged array once. They expose typed values for application runtime, database, queue, AWS, Redis, logging, HTTP, and JSON Web Token settings.

AWS and queue aggregates return immutable leaf objects. SQS, SNS, RabbitMQ, readiness, and AWS client adapters receive those leaf objects directly. Only an external Software Development Kit builder converts typed settings into an SDK array.

## Precedence and loading

- `backendbaseEnv()` reads `$_ENV`, then the process environment, then a code default.
- An empty string is present and does not activate the default.
- Object-storage configuration explicitly treats an empty `OBJECT_STORE_ENDPOINT` as a request to use `AWS_ENDPOINT`.
- Shared `config/autoload` providers load before the selected API providers.
- Process values are not replaced by local Dotenv values.
- Most values remain strings unless configuration parses them.
- `backendbaseIntegerEnvironmentValue()` and `backendbaseFloatEnvironmentValue()` reject malformed numeric text during configuration loading.

Use exact `BACKENDBASE_ENV` values: `dev`, `test`, `ci`, `stage`, or `production`.

The public API can cache merged configuration, the container, proxies, and routes. Clear cache after environment, route, or dependency changes:

```sh
bin/backendbase clear-cache
```

A cached public boot skips Dotenv. Restart long-running workers after configuration changes.

There is no single configuration schema. Focused settings objects validate required nested values when PHP-DI resolves each lazy dependency.

`RedisJsonInterface` uses a PHP-DI lazy proxy. Resolving JWT services does not construct the native Redis client or open a socket. The first Redis command initializes the proxy and connection. The Redis readiness `PING` intentionally performs this initialization. Doctrine DBAL also delays its MySQL socket until the first database operation.

Runtime components do not call `Settings::get()`. Configuration boundary objects are the only application classes that read the generic merged settings.

Numeric configuration uses strict integer and finite-float parsers. Error messages identify the invalid key without exposing its value.

Treat `.env.example` as a local aid, not an authoritative variable catalog. Keep database, broker, AWS, JWT, API, object-store, and Tolgee secrets outside source and logs.

Basis: `resources/docs/7-env-and-config.html`.
