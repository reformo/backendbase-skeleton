<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleGroupPage;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetExampleGroupsByType, ExampleGroupPage> */
class GetExampleGroupsByTypeHandler implements QueryHandler
{
    public function __construct(private readonly ExampleReadRepository $exampleRepository)
    {
    }

    /** @param GetExampleGroupsByType $query */
    public function handle(Query $query): ExampleGroupPage
    {
        return $this->exampleRepository->getExampleGroupsByType($query);
    }
}
