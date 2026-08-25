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
- `Content` is an empty placeholder. Do not use it as a reference.
- Run project commands from the repository root.

Basis: `resources/docs/0-project.html`.
