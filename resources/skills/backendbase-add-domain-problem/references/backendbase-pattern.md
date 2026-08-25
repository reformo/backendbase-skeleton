# Backendbase domain-problem pattern

## Role-to-target mapping

| Failure | Owner |
| --- | --- |
| Business invariant violation | Owning context domain |
| Use-case input or missing application resource | Owning application or stable Shared problem when meaning is truly common |
| Vendor, database, queue, or network failure | Infrastructure adapter translation boundary |
| HTTP routing or method failure | Shared HTTP error handler |
| Unexpected programming failure | Global safe server-error path |

## Small problem type

```php
final class CatalogItemNotFound extends DomainException
{
    public const int STATUS = 404;
    public const string TYPE = 'about:blank';
    public const string CODE = 'catalog/item-not-found';
    public const string TITLE = 'Catalog Item Not Found';
}
```

Usage:

```php
throw CatalogItemNotFound::create(
    'The catalog item was not found.',
    ['itemId' => $itemId->toString()],
);
```

Include `itemId` only when the identifier is safe and the public contract needs it.

## Public response path

- The exception provides status, type, code, title, detail, and approved additional data through `ProblemDetailsException` methods.
- `Action` catches known `ProblemDetailsException` values.
- `ProblemDetailsResponseFactory` builds an `application/problem+json` response and adds the stable code through `ActionError`.
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

- Current stable examples define `STATUS`, `TYPE`, `CODE`, and `TITLE` constants.
- Some older primitive exceptions still use legacy protected fields. Do not copy that older form for new problem types.
- `DomainExceptionProblemDetails::toArray()` does not itself add the `code` field. The HTTP response path adds it through `ActionError`.
- Expected HTTP problems outside statuses 400, 401, 403, and 404 are logged as server failures by the current response factory.
- Additional data can be translated or used for message parameter replacement and must remain public-safe.

## Verification map

```sh
vendor/bin/phpunit tests/Domain/Catalog/Exception
vendor/bin/phpunit tests/Shared/Http/Actions
vendor/bin/phpunit tests/Shared/Http/Handlers
vendor/bin/phpunit tests/Architecture
composer phpstan
composer cs-check
```

## Source provenance

- `resources/docs/11-error-handling-and-observability.html`
- `resources/platform/16-errors-observability.md`
- `src/Backendbase/Shared/Domain/Exception/DomainException.php`
- `src/Backendbase/Shared/Domain/Exception/DomainExceptionProblemDetails.php`
- `src/Backendbase/Shared/Exception/ResourceNotFound.php`
- `src/Backendbase/Shared/Exception/InvalidUserInput.php`
- `src/Backendbase/Domain/IdentityAndAccess/Exception/AuthorizationExpired.php`
- `src/Backendbase/Shared/Http/Actions/Action.php`
- `src/Backendbase/Shared/Http/Actions/ProblemDetailsResponseFactory.php`
- `tests/Shared/Http/Actions/ProblemDetailsResponseFactoryTest.php`
- `tests/Shared/Domain/SharedDomainSupportTest.php`
