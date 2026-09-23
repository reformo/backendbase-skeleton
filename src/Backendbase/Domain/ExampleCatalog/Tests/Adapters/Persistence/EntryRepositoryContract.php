<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Domain\Exception\EntryAlreadyExists;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\Pagination;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

trait EntryRepositoryContract
{
    use EntryGroupPaginationContract;

    abstract protected function readRepository(): EntryReadRepository;

    abstract protected function writeRepository(): EntryWriteRepository;

    #[Test]
    public function itReturnsEmptyResultsFromAnEmptyRepository(): void
    {
        self::assertNull($this->readRepository()->getEntryByCriteria(
            new GetEntryByCriteria(EntryType::SYSTEM, null, 'settings', 'missing'),
        ));
        $page = $this->readRepository()->getEntriesByGroup(
            new GetEntriesByGroup(EntryType::SYSTEM, null, 'settings', new Pagination(10, 1)),
        );
        self::assertSame(0, $page->total());
    }

    #[Test]
    public function itWritesAndReadsEntryProjections(): void
    {
        $firstEntryId  = Uuid::uuid7()->toString();
        $secondEntryId = Uuid::uuid7()->toString();
        $thirdEntryId  = Uuid::uuid7()->toString();
        $fourthEntryId = Uuid::uuid7()->toString();
        $this->addEntry($firstEntryId, 'settings', 'first', 'one');
        $this->addEntry($secondEntryId, 'settings', 'second', 'two');
        $this->addEntry($thirdEntryId, 'features', 'third', 'three');
        $this->addEntry($fourthEntryId, 'features', 'targeted', 'four', 42);

        $groups = $this->readRepository()->getEntryGroupsByType(
            new GetEntryGroupsByType(EntryType::SYSTEM, null),
        );
        self::assertSame(['features', 'settings'], $groups->items());
        self::assertSame(2, $groups->total());

        $page = $this->readRepository()->getEntriesByGroup(
            new GetEntriesByGroup(EntryType::SYSTEM, null, 'settings', new Pagination(1, 2)),
        );
        self::assertSame(2, $page->total());
        self::assertSame($secondEntryId, $page->items()[0]->uuid());

        $details = $this->readRepository()->getEntryByCriteria(
            new GetEntryByCriteria(EntryType::SYSTEM, 42, 'features', 'targeted'),
        );
        self::assertNotNull($details);
        self::assertSame('four', $details->lookupValue());
        self::assertSame(['image' => 'example.png'], $details->details());
    }

    #[Test]
    public function itChangesAndSoftDeletesOnlyActiveEntries(): void
    {
        $entryId = Uuid::uuid7()->toString();
        $this->addEntry($entryId, 'settings', 'first', 'one');
        $identity = new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'first');
        $entry    = $this->writeRepository()->getActiveByIdentity($identity);
        self::assertSame($entryId, $entry->id());
        $entry->change(false, 'changed', ['changed' => true]);
        $this->writeRepository()->save($entry);

        $details = $this->readRepository()->getEntryByCriteria(
            new GetEntryByCriteria(EntryType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertNotNull($details);
        self::assertSame('changed', $details->lookupValue());
        self::assertFalse($details->isActive());
        self::assertSame(['changed' => true], $details->details());

        $entry = $this->writeRepository()->getActive($entryId);
        $entry->remove();
        $this->writeRepository()->save($entry);
        self::assertNull($this->readRepository()->getEntryIdByCriteria(
            new GetEntryIdByCriteria(EntryType::SYSTEM, null, 'settings', 'first'),
        ));

        $this->expectException(ResourceNotFound::class);
        $this->writeRepository()->getActiveByIdentity($identity);
    }

    #[Test]
    public function itRejectsDuplicateActiveEntriesWithoutATypeTarget(): void
    {
        $this->addEntry(Uuid::uuid7()->toString(), 'settings', 'first', 'one');

        $this->expectException(EntryAlreadyExists::class);
        $this->addEntry(Uuid::uuid7()->toString(), 'settings', 'first', 'duplicate');
    }

    #[Test]
    public function itAllowsAReplacementAfterTheExistingEntryIsSoftDeleted(): void
    {
        $firstEntryId  = Uuid::uuid7()->toString();
        $secondEntryId = Uuid::uuid7()->toString();
        $this->addEntry($firstEntryId, 'settings', 'first', 'one');
        $entry = $this->writeRepository()->getActive($firstEntryId);
        $entry->remove();
        $this->writeRepository()->save($entry);

        $this->addEntry($secondEntryId, 'settings', 'first', 'replacement');

        $storedEntryId = $this->readRepository()->getEntryIdByCriteria(
            new GetEntryIdByCriteria(EntryType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertSame($secondEntryId, $storedEntryId);
    }

    protected function addEntry(
        string $entryId,
        string $group,
        string $key,
        string $value,
        int|null $typeTargetId = null,
    ): void {
        $this->writeRepository()->add(Entry::create(
            $entryId,
            EntryType::SYSTEM,
            $typeTargetId,
            $group,
            true,
            $key,
            $value,
            ['image' => 'example.png'],
        ));
    }
}
