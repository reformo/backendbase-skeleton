<?php

declare(strict_types=1);

namespace Tests\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Domain\ExampleBoundedContext\Adapters\Persistence\ExampleRepositoryContract;

final class ExampleRepositoryTest extends DoctrineExampleRepositoryTestCase
{
    use ExampleRepositoryContract;

    #[Test]
    public function itRollsBackOrmWritesThroughTheSharedDbalConnection(): void
    {
        $exampleId = Uuid::uuid7()->toString();

        try {
            $this->connection->transactional(function () use ($exampleId): void {
                $this->addExample($exampleId, 'settings', 'first', 'one');

                throw new RuntimeException('Force transaction rollback.');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('Force transaction rollback.', $exception->getMessage());
        }

        $storedExampleId = $this->readRepository->getExampleIdByCriteria(
            new GetExampleIdByCriteria(ExampleType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertNull($storedExampleId);
    }
}
