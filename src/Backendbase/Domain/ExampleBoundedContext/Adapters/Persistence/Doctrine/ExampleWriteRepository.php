<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\Entity\ExampleRecord;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as ExampleWriteRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Domain\ExampleBoundedContext\Domain\Exception\ExampleAlreadyExists;
use Backendbase\Shared\Exception\ResourceNotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ExampleWriteRepository implements ExampleWriteRepositoryContract
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Example $example): void
    {
        try {
            $this->entityManager->persist(ExampleRecord::fromDomain($example));
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw ExampleAlreadyExists::create(
                'An active example already uses this identity.',
                previous: $exception,
            );
        }
    }

    public function getActive(string $exampleId): Example
    {
        $record = $this->getActiveRecord(['uuid' => $exampleId]);

        return $record->toDomain();
    }

    public function getActiveByIdentity(ExampleIdentity $identity): Example
    {
        $type         = $identity->type();
        $typeTargetId = $identity->typeTargetId();
        $group        = $identity->group();
        $key          = $identity->key();

        $record = $this->getActiveRecord([
            'type' => $type,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'lookupKey' => $key,
        ]);

        return $record->toDomain();
    }

    public function save(Example $example): void
    {
        $exampleId = $example->id();
        $record    = $this->getActiveRecord(['uuid' => $exampleId]);
        $record->synchronize($example);
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $criteria */
    private function getActiveRecord(array $criteria): ExampleRecord
    {
        $criteria['deletedAt'] = null;
        $entityManager         = $this->entityManager;
        $repository            = $entityManager->getRepository(ExampleRecord::class);
        $record                = $repository->findOneBy($criteria);
        if (! $record instanceof ExampleRecord) {
            throw ResourceNotFound::create('The example was not found.');
        }

        return $record;
    }
}
