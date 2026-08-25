<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExamplePage;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetExamplesByGroup, ExamplePage> */
class GetExamplesByGroupHandler implements QueryHandler
{
    public function __construct(private readonly ExampleReadRepository $exampleRepository)
    {
    }

    /** @param GetExamplesByGroup $query */
    public function handle(Query $query): ExamplePage
    {
        return $this->exampleRepository->getExamplesByGroup($query);
    }
}
