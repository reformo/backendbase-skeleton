<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Domain;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Domain\Value\EntryDetails;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class EntryDetailsValidationTest extends TestCase
{
    private const string IDENTIFIER = '018f0000-0000-7000-8000-000000000001';

    /** @param array<string, mixed> $details */
    #[Test]
    #[DataProvider('unreadableDetails')]
    public function itRejectsUnreadableDetailsDuringCreation(array $details): void
    {
        $this->expectException(InvalidUserInput::class);
        Entry::create(self::IDENTIFIER, EntryType::SYSTEM, null, 'settings', true, 'key', 'before', $details, new DateTimeImmutable('2026-10-16T10:00:00Z'));
    }

    /** @param array<string, mixed> $details */
    #[Test]
    #[DataProvider('unreadableDetails')]
    public function itRejectsUnreadableDetailsInDirectCommands(array $details): void
    {
        $this->expectException(InvalidUserInput::class);
        new AddEntry(self::IDENTIFIER, EntryType::SYSTEM, null, 'settings', true, 'key', 'before', $details);
    }

    /** @param array<string, mixed> $details */
    #[Test]
    #[DataProvider('unreadableDetails')]
    public function itKeepsEveryEntryFieldUnchangedWhenDetailsAreRejected(array $details): void
    {
        $createdAt = new DateTimeImmutable('2026-10-16T10:00:00Z');
        $entry     = Entry::create(self::IDENTIFIER, EntryType::SYSTEM, null, 'settings', true, 'key', 'before', ['label' => 'original'], $createdAt);
        try {
            $entry->change(false, 'after', $details, new DateTimeImmutable('2026-10-16T11:00:00Z'));
            self::fail('Unreadable details must fail before the entry changes.');
        } catch (InvalidUserInput) {
            $state = $entry->snapshot();
            self::assertTrue($state->isActive());
            self::assertSame('before', $state->lookupValue());
            self::assertSame(['label' => 'original'], $state->details());
            self::assertSame($createdAt, $state->updatedAt());
        }
    }

    /** @param array<string, mixed> $details */
    #[Test]
    #[DataProvider('unreadableDetails')]
    public function itKeepsThePreviousCommandDetailsWhenAnUpdateIsRejected(array $details): void
    {
        $command = new ChangeEntry(new EntryIdentity(EntryType::SYSTEM, null, 'settings', 'key'));
        $command->setDetails(['label' => 'original']);
        try {
            $command->setDetails($details);
            self::fail('Unreadable details must fail before the command changes.');
        } catch (InvalidUserInput) {
            self::assertSame(['label' => 'original'], $command->details());
        }
    }

    #[Test]
    public function itRetainsReadableDetailsAtTheDepthBoundary(): void
    {
        $values  = ['nested' => self::nestedValues(62)];
        $details = new EntryDetails($values);
        self::assertSame(json_encode($values, JSON_THROW_ON_ERROR), json_encode($details->toArray(), JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function unreadableDetails(): iterable
    {
        yield 'encoder depth limit' => [['nested' => self::nestedValues(64)]];
        yield 'decoder depth limit' => [['nested' => self::nestedValues(63)]];
        yield 'top-level NUL name' => [["\0hidden" => 'must not disappear']];
        yield 'nested NUL name' => [['nested' => ["\0hidden" => 'must not fail later']]];
        yield 'nested object NUL name' => [['nested' => (object) ["\0hidden" => 'must not disappear']]];
    }

    /** @return array<string, mixed> */
    private static function nestedValues(int $depth): array
    {
        $nested = ['leaf' => 'value'];
        for ($level = 1; $level < $depth; $level++) {
            $nested = ['child' => $nested];
        }

        return $nested;
    }
}
