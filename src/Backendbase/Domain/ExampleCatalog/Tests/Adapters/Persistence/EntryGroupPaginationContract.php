<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Primitives\Pagination;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

use function sprintf;

trait EntryGroupPaginationContract
{
    #[Test]
    public function itPaginatesBeyondOneThousandDistinctGroups(): void
    {
        for ($groupNumber = 1; $groupNumber <= 1001; ++$groupNumber) {
            $this->addEntry(Uuid::uuid7()->toString(), sprintf('group-%04d', $groupNumber), 'key', 'value');
        }

        $this->addEntry(Uuid::uuid7()->toString(), 'group-1001', 'second-key', 'value');
        $repository = $this->readRepository();
        $page       = $repository->getEntryGroupsByType(
            new GetEntryGroupsByType(EntryType::SYSTEM, null, new Pagination(1000, 2)),
        );

        self::assertSame(1001, $page->total());
        self::assertSame(['group-1001'], $page->items());
    }

    #[Test]
    public function itReturnsAnEmptyGroupPageWithTheCompleteTotal(): void
    {
        $this->addEntry(Uuid::uuid7()->toString(), 'settings', 'key', 'value');
        $repository = $this->readRepository();
        $page       = $repository->getEntryGroupsByType(
            new GetEntryGroupsByType(EntryType::SYSTEM, null, new Pagination(10, 2)),
        );

        self::assertSame(1, $page->total());
        self::assertSame([], $page->items());
    }

    #[Test]
    public function itReturnsAnEmptyGroupPageWhenNoGroupsMatch(): void
    {
        $repository = $this->readRepository();
        $page       = $repository->getEntryGroupsByType(
            new GetEntryGroupsByType(EntryType::SYSTEM, null, new Pagination(10, 1)),
        );

        self::assertSame(0, $page->total());
        self::assertSame([], $page->items());
    }
}
