# Backendbase local-translation pattern

## Role-to-target map

| Role | Backendbase component | Target equivalent |
| --- | --- | --- |
| Catalog loader | PHP-DI application definitions | Target locale discovery |
| Catalog files | `resources/i18n/{locale}.php` | Target dictionary format and path |
| Lookup | `Translator` dotted-key lookup | Target translation service |
| Locale input | `Accept-Language` server value | Target request locale source |
| Tests | `SharedServicesTest` | Target lookup and placeholder tests |

## Runtime model

The container loads every `resources/i18n/*.php` file into a locale-indexed dictionary. `Translator` prefixes the requested dotted key with the selected locale and performs named replacement.

```php
return [
    'orders' => [
        'not-found' => 'Order :orderId was not found.',
    ],
];
```

```php
$translator->translate('orders.not-found', ['orderId' => $orderId]);
```

Every locale must contain `orders.not-found` and the `:orderId` placeholder. A locale can return a nested array, but the value kind must be intentional and consistent for its callers.

## Locale selection behavior

- The first comma-separated `Accept-Language` value is selected.
- An optional quality weight is removed.
- Matching is by exact locale filename.
- Missing, empty, or unsupported input selects `tr-TR` in current Backendbase.
- A missing key returns a value such as `en-US.orders.not-found`.

## Current source limitations

- `resources/i18n/en-US.php` requires and returns the Turkish dictionary, so English currently contains Turkish values.
- There is no language-range negotiation such as `en` to `en-US`.
- There is no per-key fallback.
- Do not use the current English file as a translation template.

## Exact source provenance

- `src/Backendbase/Shared/Services/Translator.php`
- `config/dependencies/application.php`
- `resources/i18n/tr-TR.php`
- `resources/i18n/en-US.php`
- `tests/Shared/Services/SharedServicesTest.php`
- `resources/docs/15-internationalization-and-tolgee.html`
- `resources/platform/21-i18n.md`

The target project controls its locales, default, and approved wording.
