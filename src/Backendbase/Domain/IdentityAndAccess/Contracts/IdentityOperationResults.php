<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;

interface IdentityOperationResults
{
    public function put(string $operationId, IdentityOperationResult $result): void;

    public function get(string $operationId): IdentityOperationResult;
}
