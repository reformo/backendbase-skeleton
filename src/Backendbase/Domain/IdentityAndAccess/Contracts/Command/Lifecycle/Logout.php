<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Lifecycle;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;

final readonly class Logout extends IdentityCommand
{
    public function __construct(string $operationId, private Principal $principal)
    {
        parent::__construct($operationId);
    }

    public function principal(): Principal
    {
        return $this->principal;
    }
}
