# Platform Identity

Backendbase Core is a modular PHP 8.5 backend foundation. It uses Domain-Driven Design, Ports and Adapters, and Command Query Responsibility Segregation (CQRS).

## Stack

- Slim 4 handles HTTP routing and middleware.
- PHP-DI builds the dependency container.
- Laminas Diactoros supplies PSR-7 messages.
- Doctrine Object-Relational Mapper (ORM) handles writes.
- Doctrine Database Abstraction Layer (DBAL) handles read projections.
- MySQL 8 stores business and message data.
- Redis stores JSON Web Token (JWT) state.
- RabbitMQ or Amazon Simple Queue Service (SQS) transports messages.
- OpenAPI defines HTTP contracts. Bruno runs executable API examples.
- PHPUnit, PHPStan level 8, and PHPCS verify changes.

## Current reference surfaces

- `ExampleApi` is the only consumer API.
- `ExampleBoundedContext` is the complete business-module reference.
- `IdentityAndAccess` supplies authentication and authorization components.
- Run project commands from the repository root.

## Composition roots

- Each bounded context must own its composition root and concrete adapter bindings.
- The global composition root must only assemble platform dependencies and discover bounded-context composition roots.

## Repository structure

```text
backendbase-core/
├── .github/workflows/
│   ├── quality-gates.yml              # Tests, static analysis, and code style
│   ├── release-artifact.yml           # Immutable release archive generation
│   └── security-checks.yml            # Static and dynamic security checks
├── bin/
│   ├── backendbase                    # Console entry point
│   ├── doctrine                       # Doctrine tooling entry point
│   ├── bruno                          # Bruno API end-to-end runner
│   ├── deployment/                    # Release build, deployment, and rollback scripts
│   ├── dev/                           # Local maintenance scripts
│   └── tolgee/                        # Translation synchronization scripts
├── config/
│   ├── autoload/                      # Shared runtime configuration
│   ├── dependencies/                  # Focused PHP-DI definition providers
│   ├── example-api/                   # ExampleApi configuration
│   ├── commands.php                   # Console command registration
│   ├── dependencies.php               # Dependency-provider loader
│   ├── doctrine-types.php             # Doctrine type registration
│   └── settings.php                   # Settings service registration
├── deployment/
│   ├── README.md                      # Immutable deployment workflow
│   └── release.json                   # Migration target and rollback policy
├── public/
│   ├── index.php                      # Shared HTTP bootstrap and API selector
│   └── example-api/
│       ├── index.php                  # ExampleApi front controller
│       └── docs/                      # Generated and served OpenAPI document
├── resources/
│   ├── api-docs/
│   │   ├── common/                    # Shared OpenAPI components
│   │   └── example-api/               # ExampleApi OpenAPI sources
│   ├── bruno/example-api/             # ExampleApi Bruno collection
│   ├── database/
│   │   ├── Migrations/                # Doctrine migrations
│   │   └── Seeders/                   # Database seeders
│   ├── docs/                          # Detailed project guides and reports
│   ├── i18n/                          # Local translation dictionaries
│   └── platform/                      # Modular agent context and task routing
├── src/Backendbase/
│   ├── Domain/
│   │   ├── ExampleBoundedContext/     # Complete bounded-context reference
│   │   │   ├── Adapters/Persistence/
│   │   │   │   ├── Doctrine/          # Production read and write adapters
│   │   │   │   └── Memory/            # In-memory test adapters
│   │   │   ├── Application/           # Command/query handlers and subscribers
│   │   │   ├── Contracts/             # Commands, queries, ports, and event contracts
│   │   │   ├── Domain/                # Aggregate, enum, and business rules
│   │   │   ├── Tests/                 # Module-owned tests
│   │   │   └── ServiceProvider.php    # Port bindings and subscriber metadata
│   │   └── IdentityAndAccess/         # Authentication and authorization components
│   ├── Infrastructure/
│   │   ├── Adapters/                  # Database, queue, notification, and object-store adapters
│   │   ├── Health/                    # Dependency readiness checks
│   │   └── UseCase/
│   │       ├── Console/               # Operational console commands
│   │       └── ExampleApi/            # ExampleApi middleware, routes, and controllers
│   └── Shared/
│       ├── CQRS/                      # Command and query buses
│       ├── Domain/                    # Base domain and messaging types
│       ├── Http/                      # HTTP actions, middleware, and error handling
│       ├── Integrations/              # External capability ports
│       ├── Persistence/               # Persistence ports and Doctrine support
│       ├── Primitives/                # Shared value objects
│       └── Services/                  # Shared application services
├── tests/                             # Architecture, domain, functional, infrastructure, and shared tests
├── var/cache/                         # Runtime caches and generated proxies
├── AGENTS.md                          # Repository agent rules
├── composer.json                      # Dependencies and project commands
├── docker-compose.yaml                # Local MySQL, Redis, and RabbitMQ services
├── migrations.json                    # Doctrine migration configuration
├── phpunit.xml                        # PHPUnit suite configuration
├── phpstan.neon                       # PHPStan level 8 configuration
└── phpcs.xml.dist                     # Doctrine coding-standard configuration
```

## Important entry points

- Shared HTTP bootstrap and API selector: `public/index.php`
- ExampleApi front controller: `public/example-api/index.php`
- ExampleApi middleware: `src/Backendbase/Infrastructure/UseCase/ExampleApi/middleware.php`
- ExampleApi routes: `src/Backendbase/Infrastructure/UseCase/ExampleApi/routes.php`
- ExampleApi module registry: `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/ModuleRoutes.php`
- ExampleApi root, liveness, readiness, and authentication handlers: `src/Backendbase/Infrastructure/UseCase/ExampleApi/Controllers/Root`
- Dependency readiness checks: `src/Backendbase/Infrastructure/Health`
- ExampleApi configuration: `config/example-api`
- Shared runtime configuration: `config/autoload`
- Main dependency wiring: `config/dependencies.php`
- Focused dependency providers: `config/dependencies`
- Console command wiring: `config/commands.php`
- ExampleApi OpenAPI source: `resources/api-docs/example-api`
- Generated ExampleApi OpenAPI document: `public/example-api/docs/example-api-merged.yml`
- ExampleApi Bruno collection: `resources/bruno/example-api`
- Bruno HTML reports: `artifacts/bruno/{collection}/{environment}.html`
- Reference bounded context: `src/Backendbase/Domain/ExampleBoundedContext`
- Doctrine migrations: `resources/database/Migrations`
- Doctrine migration configuration: `migrations.json`
- Deployment policy: `deployment/release.json`
- Deployment workflow: `deployment/README.md`
- Platform context routing: `resources/platform/README.md`
- PHPUnit suite configuration: `phpunit.xml`
- PHPStan configuration: `phpstan.neon`
- PHPCS configuration: `phpcs.xml.dist`
- Composer dependencies and scripts: `composer.json`

## Common commands

Run commands from the repository root.

```sh
# Start the local HTTP server.
composer run start-apis

# Run tests and quality checks.
composer test
composer phpstan
composer cs-check

# Generate and validate the ExampleApi OpenAPI document.
composer run generate-example-api-spec
composer run validate-example-api-spec

# Run a focused bounded-context suite.
vendor/bin/phpunit src/Backendbase/Domain/ExampleBoundedContext/Tests

# Run the ExampleApi Bruno collection against a prepared local service.
bin/bruno example-api local

# List console commands and inspect migration state.
bin/backendbase list
bin/doctrine migrations:status
```

`bin/doctrine migrations:diff` writes a migration file. Run it only for an explicitly approved schema change after repository behavior is final.

Use `bin/doctrine migrations:migrate --dry-run --no-interaction` against an identified, prepared target. Apply a migration only with explicit authority for that target database.
