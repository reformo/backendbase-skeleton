# Backendbase readiness-check pattern

Use this pattern to prove that a dependency can support new work without exposing diagnostic detail.

## Target discovery

1. Read applicable `AGENTS.md` files.
2. Inspect Composer dependencies, PSR-4 roots, health contracts, dependency factories, routes, logger behavior, and tests.
3. Find the target's liveness and readiness distinction.
4. Locate the client or project-owned port used by the dependency.
5. Determine the existing connection and request timeout controls.

Do not assume Backendbase check names, routes, object-store buckets, queue names, hosts, or credentials.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Check contract | `Shared\\Health\\ReadinessCheck` | Reuse the project-owned health contract. |
| Deferred creation | `DeferredReadinessCheck` | Prevent one missing dependency from breaking check composition. |
| Aggregation | `ReadinessChecks` | Convert exceptions into safe results. |
| HTTP adapter | `ExampleApi/.../Readiness.php` | Use the target API and route style. |
| Dependency registration | `config/dependencies/readiness.php` | Add the check through the existing container. |
| Focused test | `tests/Infrastructure/Health` | Test success and invalid response with doubles. |

## Probe design

A good readiness probe is:

- read-only;
- cheap;
- bounded by finite timeouts;
- representative of the required capability;
- safe to call repeatedly;
- free of credentials and internal messages in its public output.

Examples include `SELECT 1`, Redis `PING`, opening and closing a broker channel, reading queue attributes, or checking bucket metadata.

## Small implementation example

The client and check name are illustrative.

```php
final readonly class SearchReadinessCheck implements ReadinessCheck
{
    public function __construct(private SearchClient $client)
    {
    }

    public function name(): string
    {
        return 'search';
    }

    public function check(): void
    {
        if (! $this->client->isAvailable()) {
            throw new UnexpectedValueException('The search readiness response was invalid.');
        }
    }
}
```

The aggregator owns public translation:

```php
try {
    $check->check();
} catch (Throwable) {
    return ReadinessResult::unavailable($check->name());
}
```

Do not return exception text from the check controller.

## Workflow

1. Choose the minimal read-only operation.
2. Configure finite connection and request timeouts.
3. Implement the project-owned check contract.
4. Close temporary connections and channels in `finally` blocks.
5. Register the check lazily.
6. Preserve the target liveness endpoint without dependency calls.
7. Test success, invalid response, thrown client exception, safe 503 output, and resource cleanup.
8. Update deployment readiness documentation when the release gate changes.

## Verified Backendbase invariants and limits

- `ReadinessCheck::check()` returns `void` and reports failure by throwing.
- The aggregator catches all throwables and exposes only `ready` or `unavailable`.
- HTTP readiness returns 200 only when all checks pass. It returns 503 otherwise.
- Liveness returns a static process result and does not execute dependency checks.
- The global readiness timeout is validated from 0.1 through 10 seconds.
- RedisJSON uses a lazy PHP-DI proxy. Its readiness `PING` initializes the client and opens the bounded connection.
- The composition root configures a typed RabbitMQ connection factory for readiness.
- RabbitMQ readiness uses the factory for a temporary bounded connection and closes its channel and connection.
- Queue readiness selects RabbitMQ or SQS from the configured driver.

## Authorization boundary

Do not create remote resources, change permissions, rotate credentials, or probe production without explicit authorization. Unit tests must use doubles.

## Verification

```sh
vendor/bin/phpunit tests/Infrastructure/Health
vendor/bin/phpunit tests/Infrastructure/UseCase/ExampleApi/HealthControllersTest.php
vendor/bin/phpunit tests/Architecture
composer phpstan
composer cs-check
```

Add the new focused test file to the first command. A live readiness request is optional and requires an authorized running service.

## Completion report

Report the dependency, operation, check name, timeout, registration, public response impact, resource cleanup, tests, and skipped live probe.

## Provenance

Verified on 2026-08-29 from:

- `src/Backendbase/Shared/Health/ReadinessCheck.php`
- `src/Backendbase/Shared/Health/DeferredReadinessCheck.php`
- `src/Backendbase/Shared/Health/ReadinessChecks.php`
- `src/Backendbase/Infrastructure/Health/MySQLReadinessCheck.php`
- `src/Backendbase/Infrastructure/Health/RedisReadinessCheck.php`
- `src/Backendbase/Infrastructure/Health/RabbitMQConnectionFactory.php`
- `src/Backendbase/Infrastructure/Health/RabbitMQReadinessCheck.php`
- `src/Backendbase/Infrastructure/Adapters/Queue/RabbitMQ/PhpAmqpLibRabbitMQConnectionFactory.php`
- `src/Backendbase/Infrastructure/Health/SqsReadinessCheck.php`
- `config/dependencies/rabbitmq.php`
- `config/dependencies/redis.php`
- `config/dependencies/readiness.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Root/Liveness.php`
- `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Root/Readiness.php`
- `tests/Infrastructure/Health/ReadinessDependencyDefinitionsTest.php`
- `tests/Infrastructure/Adapters/RedisDependencyDefinitionsTest.php`
- `tests/Infrastructure/UseCase/ExampleApi/HealthControllersTest.php`
- `resources/platform/16-errors-observability.md`
- `resources/docs/11-error-handling-and-observability.html`
