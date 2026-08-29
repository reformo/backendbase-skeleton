<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Backendbase\TolgeeSync\expand;
use function Backendbase\TolgeeSync\flatten;
use function dirname;

require_once dirname(__DIR__, 2) . '/bin/tolgee/sync-support.php';

final class TolgeeSyncSupportTest extends TestCase
{
    #[Test]
    public function itFlattensAndExpandsTranslations(): void
    {
        $translations = [
            'account' => [
                'title' => 'Account',
                'status' => 'Active',
            ],
            'save' => 'Save',
        ];

        $flattened = flatten($translations, 'en-US.php');

        self::assertSame([
            'account.title' => 'Account',
            'account.status' => 'Active',
            'save' => 'Save',
        ], $flattened);
        self::assertSame([
            'account' => [
                'status' => 'Active',
                'title' => 'Account',
            ],
            'save' => 'Save',
        ], expand($flattened));
    }

    #[Test]
    public function itRejectsAKeyThatConflictsWithALeaf(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Translation key "account.title" conflicts with a leaf key.');

        expand([
            'account' => 'Account',
            'account.title' => 'Title',
        ]);
    }

    #[Test]
    public function itRejectsAnEmptyTranslationPathSegment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Translation key "account..title" contains an empty path segment.');

        expand(['account..title' => 'Title']);
    }

    #[Test]
    public function itRejectsADottedSourceKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Translation file "en-US.php" contains an empty key or a key containing ".".',
        );

        flatten(['account.title' => 'Title'], 'en-US.php');
    }
}
