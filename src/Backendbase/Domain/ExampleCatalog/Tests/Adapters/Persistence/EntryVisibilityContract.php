<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Domain\Exception\EntryAlreadyExists;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

trait EntryVisibilityContract
{
    abstract protected function readRepository(): EntryReadRepository;

    abstract protected function writeRepository(): EntryWriteRepository;

    #[Test]
    public function itReadsAndReactivatesAnInactiveEntry(): void
    {
        $id       = Uuid::uuid7()->toString();
        $identity = new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'visibility');
        $writer   = $this->writeRepository();
        $reader   = $this->readRepository();
        $writer->add(self::visibilityEntry($id, true));
        $entry    = $writer->getActiveByIdentity($identity);
        $snapshot = $entry->snapshot();
        self::assertTrue($snapshot->isActive());
        $entry->change(false, null, null, new DateTimeImmutable('2026-10-19T11:00:00Z'));
        $writer->save($entry);
        $details = $reader->getEntryByCriteria(new GetEntryByCriteria(EntryType::SYSTEM, null, 'settings', 'visibility'));
        self::assertNotNull($details);
        self::assertFalse($details->isActive());
        $entry = $writer->getActiveByIdentity($identity);
        self::assertSame($id, $entry->id());
        self::assertFalse($entry->isRemoved());
        $entry->change(true, null, null, new DateTimeImmutable('2026-10-19T12:00:00Z'));
        $writer->save($entry);
        $reloaded = $writer->getActive($id);
        self::assertFalse($reloaded->isRemoved());
        $details = $reader->getEntryByCriteria(new GetEntryByCriteria(EntryType::SYSTEM, null, 'settings', 'visibility'));
        self::assertNotNull($details);
        self::assertTrue($details->isActive());
    }

    #[Test]
    public function itReservesTheIdentityOfAnInactiveEntry(): void
    {
        $writer  = $this->writeRepository();
        $firstId = Uuid::uuid7()->toString();
        $writer->add(self::visibilityEntry($firstId, false));
        $secondId = Uuid::uuid7()->toString();
        $this->expectException(EntryAlreadyExists::class);
        $writer->add(self::visibilityEntry($secondId, true));
    }

    private static function visibilityEntry(string $id, bool $active): Entry
    {
        return Entry::create($id, EntryType::SYSTEM, null, 'settings', $active, 'visibility', 'value', [], new DateTimeImmutable('2026-10-19T10:00:00Z'));
    }
}
