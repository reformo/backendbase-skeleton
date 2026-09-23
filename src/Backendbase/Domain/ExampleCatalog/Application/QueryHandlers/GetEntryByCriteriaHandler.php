<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\QueryHandlers;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetEntryByCriteria, EntryDetails|null> */
class GetEntryByCriteriaHandler implements QueryHandler
{
    public function __construct(private readonly EntryReadRepository $entryRepository)
    {
    }

    /** @param GetEntryByCriteria $query */
    public function handle(Query $query): EntryDetails|null
    {
        return $this->entryRepository->getEntryByCriteria($query);
    }
}
