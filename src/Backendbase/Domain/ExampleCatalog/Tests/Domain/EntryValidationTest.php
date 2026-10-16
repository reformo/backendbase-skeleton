<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Domain;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Domain\Exception\DomainException;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_replace;
use function str_repeat;

use const INF;

final class EntryValidationTest extends TestCase
{
    private const string IDENTIFIER = '018f0000-0000-7000-8000-000000000001';

    /** @param array<array-key, mixed> $details */
    #[Test]
    #[DataProvider('invalidValues')]
    public function itRejectsInvalidValuesAtDomainCreation(string $entryId, int|null $typeTargetId, string $group, string $key, string $value, array $details): void
    {
        $this->expectException(DomainException::class);
        Entry::create($entryId, EntryType::SYSTEM, $typeTargetId, $group, true, $key, $value, $details, new DateTimeImmutable('2026-10-16T10:00:00Z'));
    }

    /** @param array<array-key, mixed> $details */
    #[Test]
    #[DataProvider('invalidValues')]
    public function itRejectsInvalidValuesInDirectCommands(string $entryId, int|null $typeTargetId, string $group, string $key, string $value, array $details): void
    {
        $this->expectException(DomainException::class);
        new AddEntry($entryId, EntryType::SYSTEM, $typeTargetId, $group, true, $key, $value, $details);
    }

    /** @return iterable<string, array{entryId: string, typeTargetId: int|null, group: string, key: string, value: string, details: array<array-key, mixed>}> */
    public static function invalidValues(): iterable
    {
        $valid = ['entryId' => self::IDENTIFIER, 'typeTargetId' => null, 'group' => 'settings', 'key' => 'key', 'value' => 'value', 'details' => []];

        yield 'identifier' => array_replace($valid, ['entryId' => 'invalid']);
        yield 'target zero' => array_replace($valid, ['typeTargetId' => 0]);
        yield 'target negative' => array_replace($valid, ['typeTargetId' => -1]);
        yield 'group empty' => array_replace($valid, ['group' => '']);
        yield 'group length' => array_replace($valid, ['group' => str_repeat('ü', 33)]);
        yield 'key empty' => array_replace($valid, ['key' => '']);
        yield 'key length' => array_replace($valid, ['key' => str_repeat('ü', 161)]);
        yield 'value length' => array_replace($valid, ['value' => str_repeat('ü', 2049)]);
        yield 'invalid encoding' => array_replace($valid, ['value' => "\xff"]);
        yield 'details list' => array_replace($valid, ['details' => ['x']]);
        yield 'invalid JSON' => array_replace($valid, ['details' => ['number' => INF]]);
    }

    #[Test]
    public function itValidatesAllChangesBeforeMutatingTheEntry(): void
    {
        $createdAt = new DateTimeImmutable('2026-10-16T10:00:00Z');
        $entry     = Entry::create(...self::validArguments(), now: $createdAt);
        try {
            $entry->change(false, str_repeat('v', 2049), [], new DateTimeImmutable('2026-10-16T11:00:00Z'));
            self::fail('The invalid update must fail.');
        } catch (InvalidUserInput) {
            $state = $entry->snapshot();
            self::assertTrue($state->isActive());
            self::assertSame('value', $state->lookupValue());
            self::assertSame($createdAt, $state->updatedAt());
        }
    }

    #[Test]
    public function itRejectsInvalidPersistedValuesDuringReconstitution(): void
    {
        $now = new DateTimeImmutable('2026-10-16T10:00:00Z');
        $this->expectException(InvalidUserInput::class);
        Entry::reconstitute(self::IDENTIFIER, EntryType::SYSTEM, null, 'settings', 'key', 'value', ['x'], true, $now, $now, null);
    }

    /** @return array{entryId: string, type: EntryType, typeTargetId: int|null, group: string, isActive: bool, key: string, value: string, details: array<string, mixed>} */
    private static function validArguments(): array
    {
        return ['entryId' => self::IDENTIFIER, 'type' => EntryType::SYSTEM, 'typeTargetId' => null, 'group' => 'settings', 'isActive' => true, 'key' => 'key', 'value' => 'value', 'details' => []];
    }
}
