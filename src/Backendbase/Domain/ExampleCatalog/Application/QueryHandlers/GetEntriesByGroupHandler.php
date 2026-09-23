<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\QueryHandlers;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetEntriesByGroup, EntryPage> */
class GetEntriesByGroupHandler implements QueryHandler
{
    public function __construct(private readonly EntryReadRepository $entryRepository)
    {
    }

    /** @param GetEntriesByGroup $query */
    public function handle(Query $query): EntryPage
    {
        return $this->entryRepository->getEntriesByGroup($query);
    }
}
