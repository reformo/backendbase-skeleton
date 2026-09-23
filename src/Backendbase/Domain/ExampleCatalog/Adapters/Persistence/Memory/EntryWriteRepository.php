<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository as EntryWriteRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Shared\Exception\ResourceNotFound;

final readonly class EntryWriteRepository implements EntryWriteRepositoryContract
{
    public function __construct(private EntryStore $store)
    {
    }

    public function add(Entry $entry): void
    {
        $this->store->add($entry);
    }

    public function getActive(string $entryId): Entry
    {
        $entry = $this->store->get($entryId);
        if ($entry === null || $entry->isRemoved()) {
            throw ResourceNotFound::create('The example was not found.');
        }

        return $entry;
    }

    public function getActiveByIdentity(EntryIdentity $identity): Entry
    {
        $entry = $this->store->getByIdentity($identity);
        if ($entry === null) {
            throw ResourceNotFound::create('The example was not found.');
        }

        return $entry;
    }

    public function save(Entry $entry): void
    {
        $this->store->save($entry);
    }
}
