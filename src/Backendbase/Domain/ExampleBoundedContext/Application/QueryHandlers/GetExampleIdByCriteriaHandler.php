<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetExampleIdByCriteria, string|null> */
class GetExampleIdByCriteriaHandler implements QueryHandler
{
    public function __construct(private readonly ExampleReadRepository $exampleRepository)
    {
    }

    /** @param GetExampleIdByCriteria $query */
    public function handle(Query $query): string|null
    {
        return $this->exampleRepository->getExampleIdByCriteria($query);
    }
}
