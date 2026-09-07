# Backendbase domain-problem pattern

## Role-to-target mapping

| Failure | Owner |
| --- | --- |
| Business invariant violation | Owning context domain |
| Use-case input or missing application resource | Owning application or stable Shared problem when meaning is truly common |
| Vendor, database, queue, or network failure | Infrastructure adapter translation boundary |
| HTTP routing or method failure | Infrastructure HTTP error handler |
| Unexpected programming failure | Global safe server-error path |

## Small problem type

```php
final class CatalogItemNotFound extends DomainException
{
}
```

Usage:

```php
throw CatalogItemNotFound::create(
    'The catalog item was not found.',
    ['itemId' => $itemId->toString()],
);
```

The domain error contains only its message and safe context. It does not contain HTTP or Problem Details fields.

Add the public contract to the Infrastructure mapper:

```php
$exception instanceof CatalogItemNotFound => [
    404,
    'Catalog Item Not Found',
    'catalog/item-not-found',
    'about:blank',
],
```

Include `itemId` only when the identifier is safe and the public contract needs it.

## Public response path

- The exception provides a message and safe context through the pure `DomainException` base.
- Slim passes the exception to the Infrastructure `HttpErrorHandler`.
- `DomainErrorProblemDetailsMapper` builds the stable status, type, code, and title values.
- `ActionError` serializes the final `application/problem+json` response.
- Translation and parameter replacement occur at the HTTP boundary, not in domain code.
- Server-side diagnostic context remains in logs.

## Contract rules

- Use one code for one stable client meaning.
- Prefer an existing type when meaning and status match exactly.
- Keep details safe for all environments.
- Treat all additional data as public response data.
- Preserve the previous exception when translating an underlying failure.
- Test the final HTTP representation rather than assuming the exception's own array form is identical to the response body.

## Current source behavior and limitations

- Current domain errors are empty semantic types derived from `DomainException`.
- `DomainException` stores only a message and safe context.
- The HTTP mapper contains the public contract for every known domain error.
- Context can be translated or used for message parameter replacement and must remain public-safe.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Exception
vendor/bin/phpunit tests/Infrastructure/Adapters/Http
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
```

## Source provenance

- `resources/docs/11-error-handling-and-observability.html`
- `resources/platform/16-errors-observability.md`
- `src/Backendbase/Shared/Domain/Exception/DomainException.php`
- `src/Backendbase/Shared/Exception/ResourceNotFound.php`
- `src/Backendbase/Shared/Exception/InvalidUserInput.php`
- `src/Backendbase/Domain/IdentityAndAccess/Exception/AuthorizationExpired.php`
- `src/Backendbase/Infrastructure/Adapters/Http/DomainErrorProblemDetailsMapper.php`
- `src/Backendbase/Infrastructure/Adapters/Http/HttpErrorHandler.php`
- `tests/Infrastructure/Adapters/Http/DomainErrorProblemDetailsMapperTest.php`
- `tests/Shared/Domain/SharedDomainSupportTest.php`
