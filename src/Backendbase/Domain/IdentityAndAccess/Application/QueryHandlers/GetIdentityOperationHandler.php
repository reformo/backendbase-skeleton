<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\QueryHandlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetIdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetIdentityOperation, IdentityOperationResult> */
final readonly class GetIdentityOperationHandler implements QueryHandler
{
    public function __construct(private IdentityOperationResults $results)
    {
    }

    public function handle(Query $query): IdentityOperationResult
    {
        $currentResults = $this->results;

        return $currentResults->get($query->operationId());
    }
}
