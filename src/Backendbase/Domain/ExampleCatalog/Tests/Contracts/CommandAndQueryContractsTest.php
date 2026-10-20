<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Contracts;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Primitives\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandAndQueryContractsTest extends TestCase
{
    #[Test]
    #[DataProvider('invalidReplacements')]
    public function itPreservesChangesAfterInvalidReplacementAndClearsOmittedFields(string $field): void
    {
        $command = new ChangeEntry(new EntryIdentity(EntryType::USER, 42, 'settings', 'theme'));
        $command->setIsActive(false);
        $command->setValue('dark');
        $command->setDetails(['contrast' => 'high']);
        $before = $command->toArray();
        try {
            match ($field) {
                'details' => $command->setDetails(['invalid list']),
                default => $command->setValue("\xff"),
            };
            self::fail('Invalid replacement must fail before changing the command.');
        } catch (InvalidUserInput) {
            self::assertSame($before, $command->toArray());
        }

        $command->setIsActive(null);
        $command->setValue(null);
        $command->setDetails(null);
        self::assertSame([
            'identity' => $before['identity'],
            'isActive' => null,
            'value' => null,
            'details' => null,
        ], $command->toArray());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReplacements(): iterable
    {
        yield 'details must remain an object' => ['details'];
        yield 'the value must use UTF-8' => ['value'];
    }

    #[Test]
    public function itSerializesEntryCommands(): void
    {
        $add = new AddEntry(
            '018f0000-0000-7000-8000-000000000001',
            EntryType::USER,
            42,
            'settings',
            true,
            'theme',
            'dark',
            null,
        );
        self::assertSame([], $add->details());
        self::assertSame($add->toArray(), $add->jsonSerialize());

        $identity = new EntryIdentity(EntryType::USER, 42, 'settings', 'theme');
        $change   = new ChangeEntry($identity);
        self::assertSame($identity, $change->identity());
        self::assertNull($change->isActive());
        self::assertNull($change->value());
        self::assertNull($change->details());
        self::assertSame($change, $change->setIsActive(false));
        self::assertSame($change, $change->setValue('light'));
        self::assertSame($change, $change->setDetails(['contrast' => 'high']));
        self::assertSame([
            'identity' => [
                'type' => 'user',
                'typeTargetId' => 42,
                'group' => 'settings',
                'key' => 'theme',
            ],
            'isActive' => false,
            'value' => 'light',
            'details' => ['contrast' => 'high'],
        ], $change->jsonSerialize());

        $remove = new RemoveEntry($identity);
        self::assertSame($identity, $remove->identity());
        self::assertSame(['identity' => $identity->toArray()], $remove->toArray());
        self::assertSame($remove->toArray(), $remove->jsonSerialize());
    }

    #[Test]
    public function itExposesAndSerializesEntryQueries(): void
    {
        $byCriteria = new GetEntryByCriteria(EntryType::USER, 42, 'settings', 'theme');
        self::assertSame('settings', $byCriteria->group());
        self::assertSame(EntryType::USER, $byCriteria->type());
        self::assertSame(42, $byCriteria->typeTargetId());
        self::assertSame('theme', $byCriteria->key());
        self::assertSame($byCriteria->toArray(), $byCriteria->jsonSerialize());

        $idByCriteria = new GetEntryIdByCriteria(EntryType::USER, 42, 'settings', 'theme');
        self::assertSame('settings', $idByCriteria->group());
        self::assertSame(EntryType::USER, $idByCriteria->type());
        self::assertSame(42, $idByCriteria->typeTargetId());
        self::assertSame('theme', $idByCriteria->key());
        self::assertSame($idByCriteria->toArray(), $idByCriteria->jsonSerialize());

        $groups = new GetEntryGroupsByType(EntryType::SYSTEM, null);
        self::assertSame(EntryType::SYSTEM, $groups->type());
        self::assertNull($groups->typeTargetId());
        self::assertSame([
            'type' => 'system',
            'typeTargetId' => null,
            'pagination' => $groups->pagination(),
        ], $groups->jsonSerialize());

        $pagination = new Pagination(25, 2);
        $byGroup    = new GetEntriesByGroup(EntryType::USER, 42, 'settings', $pagination);
        self::assertSame(EntryType::USER, $byGroup->type());
        self::assertSame(42, $byGroup->typeTargetId());
        self::assertSame('settings', $byGroup->group());
        self::assertSame($pagination, $byGroup->pagination());
        self::assertSame($byGroup->toArray(), $byGroup->jsonSerialize());
    }
}
