<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\QueryHandlers;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetEntryIdByCriteria, string|null> */
class GetEntryIdByCriteriaHandler implements QueryHandler
{
    public function __construct(private readonly EntryReadRepository $entryRepository)
    {
    }

    /** @param GetEntryIdByCriteria $query */
    public function handle(Query $query): string|null
    {
        return $this->entryRepository->getEntryIdByCriteria($query);
    }
}
