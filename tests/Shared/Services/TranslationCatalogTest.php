<?php

declare(strict_types=1);

namespace Tests\Shared\Services;

use Backendbase\Shared\Services\Translator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function preg_match_all;

final class TranslationCatalogTest extends TestCase
{
    #[Test]
    public function itLoadsEnglishValuesForTheEnglishLocale(): void
    {
        $english    = require 'resources/i18n/en-US.php';
        $translator = new Translator('en-US', ['en-US' => $english]);

        self::assertSame('en-US', $english['locale']);
        self::assertSame('en', $english['language']);
        self::assertSame('Unclassified error: Example', $translator->translate('general.error', ['error' => 'Example']));
        self::assertSame('The operation failed. Try again later.', $translator->translate('general.command-failed'));
    }

    #[Test]
    public function itKeepsTheSameKeysAndPlaceholdersAcrossCatalogs(): void
    {
        $english = require 'resources/i18n/en-US.php';
        $turkish = require 'resources/i18n/tr-TR.php';

        self::assertSame(array_keys($english), array_keys($turkish));
        self::assertSame(array_keys($english['general']), array_keys($turkish['general']));

        foreach ($english['general'] as $key => $value) {
            self::assertSame(self::placeholders($value), self::placeholders($turkish['general'][$key]));
        }
    }

    /** @return list<string> */
    private static function placeholders(string $value): array
    {
        preg_match_all('/:([a-zA-Z][a-zA-Z0-9]*)/', $value, $matches);

        return $matches[1];
    }
}
