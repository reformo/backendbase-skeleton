<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Input;

use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Shared\Exception\InvalidUserInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

use const INF;

final class RequestObjectInputTest extends TestCase
{
    #[Test]
    public function itPreservesKnownFieldsAndTheirOriginalTypes(): void
    {
        $payload = ['name' => '<b>text</b>', 'number' => 1, 'nullable' => null];

        self::assertSame($payload, RequestObjectInput::read($payload, ['name', 'number', 'nullable']));
        self::assertSame(1, RequestObjectInput::positiveIntegerOrNull(1, 'number'));
        self::assertNull(RequestObjectInput::positiveIntegerOrNull(null, 'number'));
    }

    #[Test]
    #[DataProvider('invalidObjects')]
    public function itRejectsInvalidOrOversizedObjects(mixed $payload): void
    {
        $this->expectException(InvalidUserInput::class);

        RequestObjectInput::read($payload, ['name']);
    }

    /** @return list<array{mixed}> */
    public static function invalidObjects(): array
    {
        return [
            [null],
            ['text'],
            [['unknown' => 'value']],
            [['name' => "\xff"]],
            [['name' => str_repeat('a', 65536)]],
            [['name' => INF]],
            [['list item']],
        ];
    }

    #[Test]
    #[DataProvider('invalidIntegers')]
    public function itRejectsCoercedAndOutOfRangeBodyIntegers(mixed $value): void
    {
        $this->expectException(InvalidUserInput::class);

        RequestObjectInput::positiveIntegerOrNull($value, 'number');
    }

    /** @return list<array{mixed}> */
    public static function invalidIntegers(): array
    {
        return [['1'], [1.0], [true], [0], [-1], [''], [[]]];
    }
}
