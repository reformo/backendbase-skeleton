# Backendbase locale pattern

## Role-to-target map

| Role | Backendbase component | Target equivalent |
| --- | --- | --- |
| Locale identifier | Exact PHP filename | Target locale naming standard |
| Discovery | Glob in application definitions | Target catalog loader |
| Default selection | `tr-TR` fallback | Target default locale |
| Request negotiation | First exact `Accept-Language` value | Target negotiation policy |
| Packaging | `resources/i18n` in release | Target build artifact rule |

## Discovery and selection

The dependency container discovers each `resources/i18n/*.php` file. The filename without `.php` becomes the locale key. Current selection:

1. Reads `HTTP_ACCEPT_LANGUAGE`.
2. Uses `tr-TR` when missing or empty.
3. Takes the first comma-separated value.
4. Removes its optional quality weight.
5. Uses `tr-TR` when no exact filename exists.

A new file has this general shape:

```php
<?php

declare(strict_types=1);

return [
    'locale' => 'fr-FR',
    'language' => 'fr',
    'direction' => 'ltr',
    'general' => [
        'error' => 'Approved text with :error',
    ],
];
```

Use target-approved text. Copy key structure and placeholders, not source-language values.

## Catalog checks

| Check | Requirement |
| --- | --- |
| Filename | Exact locale identifier |
| Key set | Same dotted paths as authoritative locale |
| Value kind | String or nested array remains compatible |
| Placeholder set | Same named placeholders in every locale |
| Direction | Correct `ltr` or `rtl` when the target uses metadata |
| Packaging | File is present in the built release |

## Current source limitations

- `en-US.php` currently requires `tr-TR.php`, so it is not an independent English catalog.
- `en` does not match `en-US.php`.
- Missing keys do not fall back to the default catalog.
- Adding negotiation or fallback is a separate behavior change and needs explicit scope.

## Exact source provenance

- `config/dependencies/application.php`
- `src/Backendbase/Shared/Services/Translator.php`
- `resources/i18n/tr-TR.php`
- `resources/i18n/en-US.php`
- `tests/Shared/Services/SharedServicesTest.php`
- `resources/platform/21-i18n.md`

Target locale standards and approved translations take precedence over these example values.
