<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts;

use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;

interface EntryWriteRepository
{
    public function add(Entry $entry): void;

    /** Return a nonremoved Entry, including one whose isActive flag is false. */
    public function getActive(string $entryId): Entry;

    /** Return a nonremoved Entry, including one whose isActive flag is false. */
    public function getActiveByIdentity(EntryIdentity $identity): Entry;

    public function save(Entry $entry): void;
}
