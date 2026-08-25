<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Application\QueryHandlers;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetExampleGroupsByType, list<string>> */
class GetExampleGroupsByTypeHandler implements QueryHandler
{
    public function __construct(private readonly ExampleReadRepository $exampleRepository)
    {
    }

    /** @param GetExampleGroupsByType $query */

    /** @return list<string> */
    public function handle(Query $query): array
    {
        return $this->exampleRepository->getExampleGroupsByType($query);
    }
}
