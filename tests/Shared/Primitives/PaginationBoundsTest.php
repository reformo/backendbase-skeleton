<?php

declare(strict_types=1);

namespace Tests\Shared\Primitives;

use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Primitives\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function intdiv;

use const PHP_INT_MAX;

final class PaginationBoundsTest extends TestCase
{
    /** @return iterable<string, array{int, int}> */
    public static function invalidPages(): iterable
    {
        yield 'zero page size' => [0, 1];
        yield 'negative page size' => [-1, 1];
        yield 'overflowing offset' => [1000, PHP_INT_MAX];
        yield 'first offset beyond the integer limit' => [2, intdiv(PHP_INT_MAX, 2) + 2];
    }

    #[Test]
    #[DataProvider('invalidPages')]
    public function itRejectsInvalidPaginationBeforeUse(int $pageSize, int $page): void
    {
        $this->expectException(InvalidUserInput::class);

        new Pagination($pageSize, $page);
    }

    #[Test]
    public function itAcceptsTheLargestRepresentableOffset(): void
    {
        $pagination = new Pagination(PHP_INT_MAX, 2);

        self::assertSame(PHP_INT_MAX, $pagination->getOffset());
    }
}
