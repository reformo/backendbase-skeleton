<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\Exception\EntryAlreadyExists;

final class EntryStore
{
    /** @var array<string, Entry> */
    private array $entries = [];

    public function add(Entry $entry): void
    {
        if (isset($this->entries[$entry->id()])) {
            throw EntryAlreadyExists::create('The example already exists.');
        }

        $this->save($entry);
    }

    public function save(Entry $entry): void
    {
        $this->rejectActiveIdentityConflict($entry);
        $this->entries[$entry->id()] = clone $entry;
    }

    public function get(string $entryId): Entry|null
    {
        if (! isset($this->entries[$entryId])) {
            return null;
        }

        return clone $this->entries[$entryId];
    }

    public function getByIdentity(EntryIdentity $identity): Entry|null
    {
        foreach ($this->entries as $entry) {
            if ($entry->isRemoved()) {
                continue;
            }

            if (! $entry->hasIdentity($identity)) {
                continue;
            }

            return clone $entry;
        }

        return null;
    }

    /** @return list<Entry> */
    public function all(): array
    {
        $entries = [];
        foreach ($this->entries as $entry) {
            $entries[] = clone $entry;
        }

        return $entries;
    }

    private function rejectActiveIdentityConflict(Entry $entry): void
    {
        if ($entry->isRemoved()) {
            return;
        }

        $state    = $entry->snapshot();
        $identity = new EntryIdentity(
            $state->type(),
            $state->typeTargetId(),
            $state->group(),
            $state->lookupKey(),
        );
        foreach ($this->entries as $storedEntry) {
            $this->rejectConflictWithStoredEntry($entry, $storedEntry, $identity);
        }
    }

    private function rejectConflictWithStoredEntry(
        Entry $entry,
        Entry $storedEntry,
        EntryIdentity $identity,
    ): void {
        if ($storedEntry->id() === $entry->id() || $storedEntry->isRemoved()) {
            return;
        }

        if (! $storedEntry->hasIdentity($identity)) {
            return;
        }

        throw EntryAlreadyExists::create('An active example already uses this identity.');
    }
}
