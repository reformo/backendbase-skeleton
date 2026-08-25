<?php

declare(strict_types=1);

namespace Tests\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\Pagination;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class ExampleRepositoryTest extends DoctrineExampleRepositoryTestCase
{
    #[Test]
    public function itReturnsEmptyResultsFromAnEmptyRepository(): void
    {
        self::assertNull($this->readRepository->getExampleByCriteria(
            new GetExampleByCriteria(ExampleType::SYSTEM, null, 'settings', 'missing'),
        ));
        $page = $this->readRepository->getExamplesByGroup(
            new GetExamplesByGroup(ExampleType::SYSTEM, null, 'settings', new Pagination(10, 1)),
        );
        self::assertSame(0, $page->total());
    }

    #[Test]
    public function itWritesAndReadsExampleProjections(): void
    {
        $firstExampleId  = Uuid::uuid7()->toString();
        $secondExampleId = Uuid::uuid7()->toString();
        $thirdExampleId  = Uuid::uuid7()->toString();
        $this->addExample($firstExampleId, 'settings', 'first', 'one');
        $this->addExample($secondExampleId, 'settings', 'second', 'two');
        $this->addExample($thirdExampleId, 'features', 'third', 'three', 42);

        $groups = $this->readRepository->getExampleGroupsByType(
            new GetExampleGroupsByType(ExampleType::SYSTEM, null),
        );
        self::assertSame(['settings'], $groups);

        $page = $this->readRepository->getExamplesByGroup(
            new GetExamplesByGroup(ExampleType::SYSTEM, null, 'settings', new Pagination(1, 2)),
        );
        self::assertSame(2, $page->total());
        self::assertSame($secondExampleId, $page->items()[0]->uuid());

        $details = $this->readRepository->getExampleByCriteria(
            new GetExampleByCriteria(ExampleType::SYSTEM, 42, 'features', 'third'),
        );
        self::assertNotNull($details);
        self::assertSame('three', $details->lookupValue());
        self::assertSame(['image' => 'example.png'], $details->details());
    }

    #[Test]
    public function itChangesAndSoftDeletesOnlyActiveExamples(): void
    {
        $exampleId = Uuid::uuid7()->toString();
        $this->addExample($exampleId, 'settings', 'first', 'one');
        $example = $this->writeRepository->getActive($exampleId);
        $example->change(false, 'changed', ['changed' => true]);
        $this->writeRepository->save($example);

        $details = $this->readRepository->getExampleByCriteria(
            new GetExampleByCriteria(ExampleType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertNotNull($details);
        self::assertSame('changed', $details->lookupValue());
        self::assertFalse($details->isActive());
        self::assertSame(['changed' => true], $details->details());

        $example = $this->writeRepository->getActive($exampleId);
        $example->remove();
        $this->writeRepository->save($example);
        $storedExampleId = $this->readRepository->getExampleIdByCriteria(
            new GetExampleIdByCriteria(ExampleType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertNull($storedExampleId);

        $this->expectException(ResourceNotFound::class);
        $this->writeRepository->getActive($exampleId);
    }

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

    #[Test]
    public function itRejectsDuplicateActiveExamplesWithoutATypeTarget(): void
    {
        $this->addExample(Uuid::uuid7()->toString(), 'settings', 'first', 'one');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->addExample(Uuid::uuid7()->toString(), 'settings', 'first', 'duplicate');
    }

    #[Test]
    public function itAllowsAReplacementAfterTheExistingExampleIsSoftDeleted(): void
    {
        $firstExampleId  = Uuid::uuid7()->toString();
        $secondExampleId = Uuid::uuid7()->toString();
        $this->addExample($firstExampleId, 'settings', 'first', 'one');
        $example = $this->writeRepository->getActive($firstExampleId);
        $example->remove();
        $this->writeRepository->save($example);

        $this->addExample($secondExampleId, 'settings', 'first', 'replacement');

        $storedExampleId = $this->readRepository->getExampleIdByCriteria(
            new GetExampleIdByCriteria(ExampleType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertSame($secondExampleId, $storedExampleId);
    }
}
