<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Domain;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\Entity\ExampleTable;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryStore;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class EntryTest extends TestCase
{
    #[Test]
    public function itCreatesAValidEntry(): void
    {
        $createdAt = new DateTimeImmutable('2026-10-16T10:00:00.123456Z');
        $entry     = Entry::create(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            ['unit' => 'items'],
            $createdAt,
        );
        $state     = $entry->snapshot();

        self::assertSame('example-id', $entry->id());
        self::assertSame(EntryType::SYSTEM, $state->type());
        self::assertSame('25', $state->lookupValue());
        self::assertSame(['unit' => 'items'], $state->details());
        self::assertTrue($state->isActive());
        self::assertSame($createdAt, $state->createdAt());
        self::assertSame($createdAt, $state->updatedAt());
        self::assertFalse($entry->isRemoved());
    }

    #[Test]
    public function itOwnsChangeAndRemovalRules(): void
    {
        $initialDate = new DateTimeImmutable('2026-01-01 00:00:00');
        $entry       = Entry::reconstitute(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            'page-size',
            '25',
            ['unit' => 'items'],
            true,
            $initialDate,
            $initialDate,
            null,
        );

        $changedAt = new DateTimeImmutable('2026-02-01T10:00:00.234567Z');
        $entry->change(false, null, ['unit' => 'rows'], $changedAt);
        $changedState = $entry->snapshot();

        self::assertFalse($changedState->isActive());
        self::assertSame('25', $changedState->lookupValue());
        self::assertSame(['unit' => 'rows'], $changedState->details());
        self::assertSame($initialDate, $changedState->createdAt());
        self::assertSame($changedAt, $changedState->updatedAt());
        self::assertNull($changedState->removedAt());

        $removedAt = new DateTimeImmutable('2026-03-01T11:00:00.345678Z');
        $entry->remove($removedAt);
        $removedState = $entry->snapshot();

        self::assertTrue($entry->isRemoved());
        self::assertSame($initialDate, $removedState->createdAt());
        self::assertSame($removedAt, $removedState->removedAt());
        self::assertSame($removedState->removedAt(), $removedState->updatedAt());
    }

    #[Test]
    public function itReturnsNullForAnUnknownStoredEntry(): void
    {
        self::assertNull((new EntryStore())->get('missing-id'));
    }

    #[Test]
    public function itExposesThePersistedRecordIdentifier(): void
    {
        $identifier = Uuid::uuid7();
        $entry      = Entry::create(
            $identifier->toString(),
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
            new DateTimeImmutable('2026-10-16T10:00:00.123456Z'),
        );
        $record     = ExampleTable::fromDomain($entry);
        self::assertSame($identifier->toString(), $record->id());
    }
}
