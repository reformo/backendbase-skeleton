<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\Entity\ExampleRecord;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as ExampleWriteRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Shared\Exception\ResourceNotFound;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ExampleWriteRepository implements ExampleWriteRepositoryContract
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Example $example): void
    {
        $this->entityManager->persist(ExampleRecord::fromDomain($example));
        $this->entityManager->flush();
    }

    public function getActive(string $exampleId): Example
    {
        return $this->getActiveRecord($exampleId)->toDomain();
    }

    public function save(Example $example): void
    {
        $record = $this->getActiveRecord($example->id());
        $record->synchronize($example);
        $this->entityManager->flush();
    }

    private function getActiveRecord(string $exampleId): ExampleRecord
    {
        $record = $this->entityManager->getRepository(ExampleRecord::class)->findOneBy([
            'uuid' => $exampleId,
            'deletedAt' => null,
        ]);
        if (! $record instanceof ExampleRecord) {
            throw ResourceNotFound::create('The example was not found.');
        }

        return $record;
    }
}
