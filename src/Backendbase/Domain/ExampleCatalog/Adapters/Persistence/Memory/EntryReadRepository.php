<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository as EntryReadRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryGroupPage;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryListItem;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;

use function array_keys;
use function array_slice;
use function count;
use function sort;

final readonly class EntryReadRepository implements EntryReadRepositoryContract
{
    public function __construct(private EntryStore $store)
    {
    }

    public function getEntryIdByCriteria(GetEntryIdByCriteria $query): string|null
    {
        $entry = $this->find($query);

        return $entry?->id();
    }

    public function getEntryByCriteria(GetEntryByCriteria $query): EntryDetails|null
    {
        $entry = $this->find($query);
        if ($entry === null) {
            return null;
        }

        $state = $entry->snapshot();

        return new EntryDetails(
            $state->uuid(),
            $state->type(),
            $state->typeTargetId(),
            $state->group(),
            $state->lookupKey(),
            $state->lookupValue(),
            $state->details(),
            $state->isActive(),
            $state->updatedAt(),
            $state->createdAt(),
        );
    }

    public function getEntryGroupsByType(GetEntryGroupsByType $query): EntryGroupPage
    {
        $groups = [];
        foreach ($this->store->all() as $entry) {
            if ($entry->isRemoved()) {
                continue;
            }

            $state = $entry->snapshot();
            if ($state->type() !== $query->type() || $state->typeTargetId() !== $query->typeTargetId()) {
                continue;
            }

            $groups[$state->group()] = true;
        }

        $groupNames = array_keys($groups);
        sort($groupNames);

        $pagination = $query->pagination();
        $offset     = $pagination->getOffset();
        $pageSize   = $pagination->pageSize();

        return new EntryGroupPage(array_slice($groupNames, $offset, $pageSize), count($groupNames));
    }

    public function getEntriesByGroup(GetEntriesByGroup $query): EntryPage
    {
        $matchingEntries = [];
        foreach ($this->store->all() as $entry) {
            if ($entry->isRemoved()) {
                continue;
            }

            $state = $entry->snapshot();
            if (
                $state->type() !== $query->type()
                || $state->group() !== $query->group()
                || $state->typeTargetId() !== $query->typeTargetId()
            ) {
                continue;
            }

            $matchingEntries[] = $state;
        }

        $total       = count($matchingEntries);
        $pageEntries = array_slice(
            $matchingEntries,
            $query->pagination()->getOffset(),
            $query->pagination()->pageSize(),
        );
        $items       = [];
        foreach ($pageEntries as $entry) {
            $items[] = new EntryListItem(
                $entry->uuid(),
                $entry->lookupKey(),
                $entry->lookupValue(),
                $entry->details(),
                $entry->isActive(),
                $entry->createdAt(),
            );
        }

        return new EntryPage($items, $total);
    }

    private function find(GetEntryByCriteria|GetEntryIdByCriteria $query): Entry|null
    {
        foreach ($this->store->all() as $entry) {
            if ($entry->isRemoved()) {
                continue;
            }

            $state = $entry->snapshot();
            if (
                $state->type() === $query->type()
                && $state->group() === $query->group()
                && $state->lookupKey() === $query->key()
                && $state->typeTargetId() === $query->typeTargetId()
            ) {
                return $entry;
            }
        }

        return null;
    }
}
