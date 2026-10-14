<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\ReadModel;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Shared\Exception\ResourceNotFound;

final class RequestIdentityResults implements IdentityOperationResults
{
    /** @var array<string, IdentityOperationResult> */
    private array $results = [];

    public function put(string $operationId, IdentityOperationResult $result): void
    {
        $this->results[$operationId] = $result;
    }

    public function get(string $operationId): IdentityOperationResult
    {
        return $this->results[$operationId] ?? throw ResourceNotFound::create('The identity operation result was not found.');
    }
}
