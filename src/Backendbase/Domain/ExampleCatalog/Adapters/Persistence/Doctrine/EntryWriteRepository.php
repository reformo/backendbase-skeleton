<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\Entity\ExampleTable;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository as EntryWriteRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\Exception\EntryAlreadyExists;
use Backendbase\Shared\Exception\ResourceNotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final readonly class EntryWriteRepository implements EntryWriteRepositoryContract
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Entry $entry): void
    {
        try {
            $this->entityManager->persist(ExampleTable::fromDomain($entry));
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw EntryAlreadyExists::create(
                'An active example already uses this identity.',
                previous: $exception,
            );
        }
    }

    public function getActive(string $entryId): Entry
    {
        $record = $this->getActiveRecord(['id' => Uuid::fromString($entryId)]);

        return $record->toDomain();
    }

    public function getActiveByIdentity(EntryIdentity $identity): Entry
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

    public function save(Entry $entry): void
    {
        $entryId = $entry->id();
        $record  = $this->getActiveRecord(['id' => Uuid::fromString($entryId)]);
        $record->synchronize($entry);
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $criteria */
    private function getActiveRecord(array $criteria): ExampleTable
    {
        $criteria['deletedAt'] = null;
        $entityManager         = $this->entityManager;
        $repository            = $entityManager->getRepository(ExampleTable::class);
        $record                = $repository->findOneBy($criteria);
        if (! $record instanceof ExampleTable) {
            throw ResourceNotFound::create('The example was not found.');
        }

        return $record;
    }
}
