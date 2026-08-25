<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleDetails;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetExampleByCriteria, ExampleDetails|null> */
class GetExampleByCriteriaHandler implements QueryHandler
{
    public function __construct(private readonly ExampleReadRepository $exampleRepository)
    {
    }

    /** @param GetExampleByCriteria $query */
    public function handle(Query $query): ExampleDetails|null
    {
        return $this->exampleRepository->getExampleByCriteria($query);
    }
}
