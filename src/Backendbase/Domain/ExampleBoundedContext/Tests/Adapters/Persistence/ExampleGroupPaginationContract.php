<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Adapters\Persistence;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Primitives\Pagination;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function sprintf;

trait ExampleGroupPaginationContract
{
    #[Test]
    public function itPaginatesBeyondOneThousandDistinctGroups(): void
    {
        for ($groupNumber = 1; $groupNumber <= 1001; ++$groupNumber) {
            $this->addExample(Uuid::uuid7()->toString(), sprintf('group-%04d', $groupNumber), 'key', 'value');
        }

        $this->addExample(Uuid::uuid7()->toString(), 'group-1001', 'second-key', 'value');
        $repository = $this->readRepository();
        $page       = $repository->getExampleGroupsByType(
            new GetExampleGroupsByType(ExampleType::SYSTEM, null, new Pagination(1000, 2)),
        );

        self::assertSame(1001, $page->total());
        self::assertSame(['group-1001'], $page->items());
    }

    #[Test]
    public function itReturnsAnEmptyGroupPageWithTheCompleteTotal(): void
    {
        $this->addExample(Uuid::uuid7()->toString(), 'settings', 'key', 'value');
        $repository = $this->readRepository();
        $page       = $repository->getExampleGroupsByType(
            new GetExampleGroupsByType(ExampleType::SYSTEM, null, new Pagination(10, 2)),
        );

        self::assertSame(1, $page->total());
        self::assertSame([], $page->items());
    }

    #[Test]
    public function itReturnsAnEmptyGroupPageWhenNoGroupsMatch(): void
    {
        $repository = $this->readRepository();
        $page       = $repository->getExampleGroupsByType(
            new GetExampleGroupsByType(ExampleType::SYSTEM, null, new Pagination(10, 1)),
        );

        self::assertSame(0, $page->total());
        self::assertSame([], $page->items());
    }
}
