<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\EntryRepositoryContract;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class EntryRepositoryTest extends DoctrineEntryRepositoryTestCase
{
    use EntryRepositoryContract;

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
