<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http;

use Backendbase\Infrastructure\Adapters\Http\ProblemDetailsMessageFormatter;
use Backendbase\Shared\Services\Translator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsMessageFormatterTest extends TestCase
{
    #[Test]
    public function itTranslatesMessagesAndReplacesParameters(): void
    {
        $translator = new Translator('en', [
            'en' => [
                'validation' => [
                    'required' => 'The :field field is required.',
                    'options' => ['first', 'second'],
                ],
            ],
        ]);

        self::assertSame(
            'The email field is required.',
            ProblemDetailsMessageFormatter::format(
                'validation.required',
                ['field' => 'email'],
                $translator,
            ),
        );
        self::assertSame(
            ['first', 'second'],
            ProblemDetailsMessageFormatter::format('validation.options', [], $translator),
        );
        self::assertSame(
            'Invalid email',
            ProblemDetailsMessageFormatter::format('Invalid :field', ['field' => 'email'], null),
        );
    }
}
