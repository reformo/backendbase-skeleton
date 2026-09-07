# Backendbase runtime configuration pattern

This reference covers environment values, merged PHP configuration, settings, dependency factories, cache effects, and tests.

## Target discovery

1. Read applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, environment helpers, configuration providers, settings objects, dependency registration, HTTP and console entry points, caches, and tests.
3. Find the nearest setting with the same type and scope.
4. Identify which processes read the value and whether they are long-running.
5. Classify the value as public configuration, protected configuration, or secret.

Do not copy `BACKENDBASE_*`, `EXAMPLE_API_*`, JWT keys, queue credentials, service names, API slugs, or default hosts into another project.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Environment read | `backendbaseEnv()` and typed numeric helpers | Reuse the target precedence and strict parsing helpers. |
| Shared structure | `config/autoload/*.php` | Place cross-entry-point settings in shared config. |
| API structure | `config/{api-slug}/*.php` | Keep delivery-specific settings with that delivery adapter. |
| Settings access | `Shared\\Settings` | Follow the target typed or array settings contract. |
| Service construction | `config/dependencies/*.php` | Validate at the factory boundary. |
| Local example | `.env.example` | Use neutral examples, never live values. |
| Effective cache | merged config, container, route, Doctrine | Identify exact invalidation and restart needs. |

## Verified loading behavior

Backendbase reads values in this order:

1. `$_ENV[$key]` when the key exists;
2. `getenv($key)` when available;
3. the code default.

An empty string is present. It does not activate the default. Most environment values remain strings until a configuration provider parses or validates them.

Object-storage configuration is an explicit exception. It normalizes an empty `OBJECT_STORE_ENDPOINT` to `AWS_ENDPOINT`.

Backendbase uses `backendbaseIntegerEnvironmentValue()` and `backendbaseFloatEnvironmentValue()` for numeric configuration. These helpers reject malformed text before the settings merge. Their exceptions name the key without including its value.

The public API merges shared providers before API-specific providers. Its cached bootstrap can skip Dotenv. Console and Doctrine entry points load Dotenv independently.

## Workflow

1. Define one owner and one configuration key.
2. Decide whether absence is valid. Avoid a default when absence must fail.
3. Parse and validate at the trust boundary.
4. Store the typed result in the target configuration structure.
5. Validate required nested arrays again in a lazy service factory before use.
6. Add the focused provider to the existing dependency loader.
7. Update the local example and every affected contract or operations document.
8. Test precedence, empty input, boundaries, invalid types, and the first operation of each lazy proxy.
9. Record cache and worker restart requirements.

## Small configuration example

The key and range are illustrative. Select them from the target contract.

```php
$timeout = filter_var(
    appEnv('APP_SEARCH_TIMEOUT_SECONDS', '2'),
    FILTER_VALIDATE_FLOAT,
);

if ($timeout === false || $timeout < 0.1 || $timeout > 10.0) {
    throw new UnexpectedValueException('The search timeout must be between 0.1 and 10 seconds.');
}

return [
    'search' => ['timeoutSeconds' => (float) $timeout],
];
```

For booleans, use `FILTER_VALIDATE_BOOL` with explicit failure handling when invalid text must be rejected. Do not use a PHP boolean cast on arbitrary environment text.

A factory must reject invalid structure before creating the client:

```php
$search = $settings->get('search');
if (! is_array($search) || ! is_float($search['timeoutSeconds'] ?? null)) {
    throw new UnexpectedValueException('The search settings are invalid.');
}
```

## Invariants and risks

- Configuration is external input.
- Numeric configuration must reject malformed and non-finite values instead of coercing them to zero.
- Secret values stay outside source, logs, exceptions, test fixtures committed with real values, and completion reports.
- Use exact supported environment modes. Backendbase aliases can select development during early bootstrap while raw settings later reject them.
- Lazy container creation means a successful container build does not prove every service setting is valid.
- A lazy proxy can defer its client factory beyond service resolution. Invoke one safe operation to test initialization.
- `.env.example` is a local aid, not an authoritative secret catalog.
- Configuration, route, container, and Doctrine caches can retain old values.
- Long-running workers keep old settings until restart.
- The HTML guide contains an old claim that `clear-cache` returns `1`. Current code and tests return success code `0`.

## Authorization boundary

Code and example-file changes do not authorize changing protected environment values, clearing a shared cache, restarting workers, or resolving a live external service. Request permission first.

## Verification

```sh
vendor/bin/phpunit tests/Shared/Helpers/HelpersTest.php
vendor/bin/phpunit tests/Shared/Services/SharedServicesTest.php
vendor/bin/phpunit tests/Shared/Configuration/ErrorDetailConfigurationTest.php
bin/backendbase clear-cache
composer phpstan
composer complexity
composer cs-check
```

Run the newly added dependency-definition test before this broader set. Run `clear-cache` only in an authorized target environment. Add a focused test that resolves the affected service without client initialization, then invokes one safe operation.

## Completion report

Report:

- each key, type, default, and validation range;
- its shared or delivery-specific owner;
- secret classification;
- affected service factories and processes;
- cache invalidation and restart requirements;
- tests run and skipped live checks.

## Provenance

Verified on 2026-08-29 from:

- `src/Backendbase/Shared/Helpers/functions.php`
- `src/Backendbase/Shared/Options/System/Environment.php`
- `src/Backendbase/Shared/Settings.php`
- `src/Backendbase/Shared/Services/Settings.php`
- `config/settings.php`
- `config/dependencies.php`
- `config/dependencies/redis.php`
- `config/autoload/global.php`
- `config/autoload/aws.global.php`
- `public/index.php`
- `bin/backendbase`
- `tests/Shared/Helpers/HelpersTest.php`
- `tests/Shared/Services/SharedServicesTest.php`
- `tests/Infrastructure/Adapters/RedisDependencyDefinitionsTest.php`
- `resources/platform/15-configuration.md`
- `resources/docs/7-env-and-config.html`
