<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Domain;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\Entity\EntryRecord;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryStore;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EntryTest extends TestCase
{
    #[Test]
    public function itCreatesAValidEntry(): void
    {
        $entry = Entry::create(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            ['unit' => 'items'],
        );
        $state = $entry->snapshot();

        self::assertSame('example-id', $entry->id());
        self::assertSame(EntryType::SYSTEM, $state->type());
        self::assertSame('25', $state->lookupValue());
        self::assertSame(['unit' => 'items'], $state->details());
        self::assertTrue($state->isActive());
        self::assertSame($state->createdAt(), $state->updatedAt());
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

        $entry->change(false, null, ['unit' => 'rows']);
        $changedState = $entry->snapshot();

        self::assertFalse($changedState->isActive());
        self::assertSame('25', $changedState->lookupValue());
        self::assertSame(['unit' => 'rows'], $changedState->details());
        self::assertGreaterThan($initialDate, $changedState->updatedAt());

        $entry->remove();
        $removedState = $entry->snapshot();

        self::assertTrue($entry->isRemoved());
        self::assertNotNull($removedState->removedAt());
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
        $entry    = Entry::create(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $record   = EntryRecord::fromDomain($entry);
        $property = new ReflectionProperty($record, 'id');
        $property->setValue($record, 42);

        self::assertSame(42, $record->id());
    }
}
