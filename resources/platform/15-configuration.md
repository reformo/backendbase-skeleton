# Environment and Configuration

Environment variables supply deployment values. PHP configuration arrays define the raw structure. PHP-DI exposes typed settings objects.

Configuration boundary objects validate the merged array once. They expose typed values for application runtime, database, queue, AWS, Redis, logging, and HTTP settings.

AWS and queue aggregates return immutable leaf objects. SQS, SNS, RabbitMQ, readiness, and AWS client adapters receive those leaf objects directly. Only an external Software Development Kit builder converts typed settings into an SDK array.

`NotificationSettings` selects SNS, Twilio, or Netgsm SMS delivery. SNS is the default. Twilio and Netgsm require credentials, a sender, and a finite timeout. It selects SES or SMTP email delivery. SES is the default. SMTP requires a host and finite timeout. A non-empty `FIREBASE_PROJECT_ID` enables push delivery. Firebase uses a finite timeout and an optional service-account path.

## Project file paths

### Configuration names

Use lowercase `kebab-case` for project-owned configuration keys, including nested keys, and configuration file names without their extensions.
Examples include `object-store.cdn-base-url`, `readiness.timeout-seconds`, and `config/autoload/aws-global.php`.
Identity configuration uses `identity.user` and `identity.admin`. Authentication realm values remain `USER` and `ADMIN`.
Subscriber registration uses `subscriber-fqcn`, `message-fqcn`, and `event-version`. External registry definitions also use `event-name`.

Preserve external names at their boundaries:

- Environment variables retain names such as `BACKENDBASE_ENV` and `AWS_REGION`.
- HTTP headers retain names such as `Access-Control-Allow-Origin`.
- AWS and HTTP client options retain `use_path_style_endpoint` and `connect_timeout`.
- Doctrine migration options retain `table_storage` and `migrations_paths`.
- Tool configuration retains required names, including `composer.json`, `phpunit.xml`, and `phpcs.xml.dist`.
- PHP class files, method names, response fields, and event payload fields keep their existing naming contracts.

The configuration loader discovers every provider through directory wildcards. Provider names do not require a `.global.php` suffix.
Use `*-global.php`, including `http-headers-global.php`, for the existing global providers.
Update custom provider paths and configuration overrides to the new names. Previous camelCase keys have no compatibility aliases.
Environment variable names, defaults, and validation rules are unchanged. Restart long-running workers when deploying the updated configuration.

`deployment/release.json` uses `migration-target` and `application-rollback-safe`.
The release builder maps these keys to `migrationTarget` and `applicationRollbackSafe` in the generated manifest.
Existing release manifests and deployment state retain their serialization contracts.

### Runtime paths

The HTTP, console, Doctrine, and PHPUnit bootstraps change the working directory to the project root.
Load project files with root-relative paths, such as `config/dependencies.php` and `resources/i18n/en-US.php`.
The default local log path is `var/logs/app.log`.
Standalone maintenance and translation tools resolve their own project root.

## Precedence and loading

- `backendbaseEnv()` reads `$_ENV`, then the process environment, then a code default.
- An empty string is present and does not activate the default.
- Object-storage configuration explicitly treats an empty `OBJECT_STORE_ENDPOINT` as a request to use `AWS_ENDPOINT`.
- Shared `config/autoload` providers load before the selected API providers.
- Process values are not replaced by local Dotenv values.
- Most values remain strings unless configuration parses them.
- `backendbaseIntegerEnvironmentValue()` and `backendbaseFloatEnvironmentValue()` reject malformed numeric text during configuration loading.

Use exact `BACKENDBASE_ENV` values: `dev`, `test`, `ci`, `stage`, or `production`.

Commands under `bin/dev` require an explicit `BACKENDBASE_ENV=dev` from the process environment or `.env`.
The shared `bin/dev/require-dev.php` check preserves process values and rejects missing, empty, unknown, or other modes, including development aliases.
Housekeeping checks before changing files. Composer checks before starting the message viewer. The viewer checks each request and returns HTTP 403 when the check fails.

Select the CQRS resolver in dependency definitions. The `config/dependencies.php` provider defaults to `RegistryHandlerResolver::class`. The registry resolver reads handler mappings from each context's `ServiceProvider::getHandlers()`. `AttributeHandlerResolver` requires attributed contracts and cannot dispatch the current commands and queries.

The public API reloads runtime configuration and builds container definitions without persistent credential caches.
It can cache routes, Doctrine metadata, and mapping metadata. Clear these caches after their sources change:

```sh
bin/backendbase clear-cache
```

The public bootstrap loads the optional `.env` file on every request. Restart long-running workers after configuration changes.

There is no single configuration schema. Focused settings objects validate required nested values when PHP-DI resolves each lazy dependency.

`RedisJsonInterface` uses a PHP-DI lazy proxy. The first Redis command initializes the proxy and connection. The Redis readiness `PING` intentionally performs this initialization. Doctrine DBAL also delays its MySQL socket until the first database operation.

Docker Compose and the security workflow use the same pinned Redis 8.10.2 image. Redis data stays under `var/docker-data/redis`. The separate Redis Insight service connects to `redis:6379` and maps container port `5540` to `BACKENDBASE_DEV_REDIS_INSIGHT_PORT`, which defaults to `8001`.

Docker Compose sets MySQL server defaults to `utf8mb4` and `utf8mb4_tr_0900_ai_ci`.
Fresh databases inherit those defaults. Existing databases and columns require a reviewed conversion.
DBAL enforces `charset=utf8mb4` separately. See [database collation procedures](operations/database-collation.md).

Runtime components do not call `Settings::get()`. Configuration boundary objects are the only application classes that read the generic merged settings.

`config/dependencies/time.php` binds `Shared/Time/Clock` to `Infrastructure/Adapters/Time/SystemClock`. It always returns UTC, independent of the process time zone. Tests can replace this binding with a fixed clock.

`config/dependencies/logger.php` sets Monolog's time zone to UTC. Logger timestamps do not depend on the date helper or the application clock.

Numeric configuration uses strict integer and finite-float parsers. Error messages identify the invalid key without exposing its value.

Treat `.env.example` as a local aid, not an authoritative variable catalog. It supplies distinct local User and Admin signing keys, a contact verification proof key, and a sender address. The public example keys decode to 32 bytes and must be replaced before deployment. Optional provider credentials, Redis authentication, and previous keys use commented examples. Enable them only when matching services or key rotation are configured. Keep live database, broker, AWS, API, object-store, and Tolgee secrets outside source and logs.

Basis: `resources/docs/7-env-and-config.html`.

Identity signing and contact code proof keys use active and previous key identifiers.
Retain a code key through all dependent pending code and 12-hour confirmation recovery windows.
Context and rate-limit digests remain independent of rotating proof keys.
Contact proof keys use `contact-proof-key` configuration and `BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY` environment names.
The previous encryption-key environment names remain fallback aliases. Canonical names take precedence. See [security policy](14-security.md).
Redis settings support optional ACL credentials and verified TLS. Malformed connection values are rejected.
Plain Redis endpoints use a null connection context. TLS endpoints use peer and peer-name verification.
The RedisJSON library reuses the lazy native `SessionRedis` connection and its Redis 8 version check.
Both session storage and readiness use that connection and its typed context. Rebuild clients after changing endpoint configuration.

HTTP write replay requires `BACKENDBASE_IDEMPOTENCY_KEY`, a dedicated base64-encoded 32-byte secret shared by API instances.
`ReplaySettings` validates it lazily. There is no deployment default.
Keep the key through retained responses and unresolved claims. See [HTTP idempotency](operations/http-idempotency.md).
