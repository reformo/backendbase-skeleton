<?php

declare(strict_types=1);

namespace Backendbase\Shared\Services;

use Adbar\Dot;

use function is_array;
use function strtr;

readonly class Translator
{
    /** @var Dot<string, mixed> */
    private Dot $dot;

    /** @param array<string, mixed> $dictionary */
    public function __construct(private string $defaultLocale, array $dictionary)
    {
        $this->dot = new Dot($dictionary);
    }

    /**
     * @param array<string, mixed>|null $values
     *
     * @return string|array<array-key, mixed>
     */
    public function translate(string $key, array|null $values = [], string|null $locale = null): string|array
    {
        $selectedLocale = $locale ?? $this->defaultLocale;
        $localeKey      = $selectedLocale . '.' . $key;
        $format         = $this->dot->get($localeKey, $localeKey);
        if (is_array($format)) {
            return $format;
        }

        $replaceArray = [];
        if (is_array($values)) {
            foreach ($values as $arrayKey => $value) {
                $replaceArray[':' . $arrayKey] = $value;
            }
        }

        return strtr($format, $replaceArray);
    }

    /**
     * @param array<string, mixed>|null $values
     *
     * @return string|array<array-key, mixed>
     */
    public function _(string $key, array|null $values = [], string|null $locale = null): string|array
    {
        return $this->translate($key, $values, $locale);
    }
}
