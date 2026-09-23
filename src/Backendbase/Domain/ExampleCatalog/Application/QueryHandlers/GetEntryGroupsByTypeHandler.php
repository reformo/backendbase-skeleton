<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Application\QueryHandlers;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryGroupPage;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetEntryGroupsByType, EntryGroupPage> */
class GetEntryGroupsByTypeHandler implements QueryHandler
{
    public function __construct(private readonly EntryReadRepository $entryRepository)
    {
    }

    /** @param GetEntryGroupsByType $query */
    public function handle(Query $query): EntryGroupPage
    {
        return $this->entryRepository->getEntryGroupsByType($query);
    }
}
