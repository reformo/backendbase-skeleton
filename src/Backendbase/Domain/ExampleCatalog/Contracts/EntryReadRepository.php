<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryGroupPage;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;

interface EntryReadRepository
{
    public function getEntryByCriteria(GetEntryByCriteria $query): EntryDetails|null;

    public function getEntryIdByCriteria(GetEntryIdByCriteria $query): string|null;

    public function getEntriesByGroup(GetEntriesByGroup $query): EntryPage;

    public function getEntryGroupsByType(GetEntryGroupsByType $query): EntryGroupPage;
}
