<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Primitives\Pagination;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function sprintf;

trait EntryOrderingContract
{
    #[Test]
    public function itOrdersByCreationTimeThenIdentifierBeforePagination(): void
    {
        $writer = $this->writeRepository();
        foreach ([[3, '11:00'], [2, '10:00'], [1, '10:00']] as [$number, $time]) {
            $identifier = sprintf('018f0000-0000-7000-8000-%012d', $number);
            $writer->add(Entry::create($identifier, EntryType::SYSTEM, null, 'settings', true, 'key-' . $number, 'value', [], new DateTimeImmutable('2026-10-16T' . $time . ':00Z')));
        }

        $reader = $this->readRepository();
        $first  = $reader->getEntriesByGroup(new GetEntriesByGroup(EntryType::SYSTEM, null, 'settings', new Pagination(2, 1)));
        $second = $reader->getEntriesByGroup(new GetEntriesByGroup(EntryType::SYSTEM, null, 'settings', new Pagination(2, 2)));
        self::assertSame(3, $first->total());
        self::assertSame(['key-1', 'key-2'], array_map(static fn ($entry): string => $entry->lookupKey(), $first->items()));
        self::assertSame('key-3', $second->items()[0]->lookupKey());
    }
}
