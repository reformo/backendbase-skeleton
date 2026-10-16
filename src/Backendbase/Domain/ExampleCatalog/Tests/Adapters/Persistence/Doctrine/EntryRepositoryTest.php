<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\EntryRepositoryContract;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class EntryRepositoryTest extends DoctrineEntryRepositoryTestCase
{
    use EntryRepositoryContract;

    #[Test]
    public function itPreservesHydratedJsonShapesAndLegacyEmptyDetailsDuringUpdates(): void
    {
        foreach (['[]', '{"nested":{},"list":[]}'] as $details) {
            $identifier = Uuid::uuid7();
            $entryId    = $identifier->toString();
            $this->connection->insert('example_table', [
                'id' => $identifier,
                'version' => 1,
                'type' => 'system',
                'lookup_group' => 'settings',
                'lookup_key' => $entryId,
                'lookup_value' => 'before',
                'details' => $details,
                'is_active' => 1,
                'created_at' => '2026-10-16 10:00:00.000000',
            ], ['id' => UuidBinaryType::NAME]);
            $entry = $this->writeRepository->getActive($entryId);
            $entry->change(null, 'after', null, new DateTimeImmutable('2026-10-16T11:00:00Z'));
            $this->writeRepository->save($entry);
            $result = $this->readRepository->getEntryByCriteria(new GetEntryByCriteria(EntryType::SYSTEM, null, 'settings', $entryId));
            self::assertNotNull($result);
            self::assertSame('after', $result->lookupValue());
            self::assertSame($details === '[]' ? '{}' : $details, json_encode((object) $result->details(), JSON_THROW_ON_ERROR));
        }
    }

    #[Test]
    public function itRollsBackOrmWritesThroughTheSharedDbalConnection(): void
    {
        $entryId = Uuid::uuid7()->toString();

        try {
            $this->connection->transactional(function () use ($entryId): void {
                $this->addEntry($entryId, 'settings', 'first', 'one');

                throw new RuntimeException('Force transaction rollback.');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('Force transaction rollback.', $exception->getMessage());
        }

        $storedEntryId = $this->readRepository->getEntryIdByCriteria(
            new GetEntryIdByCriteria(EntryType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertNull($storedEntryId);
    }
}
