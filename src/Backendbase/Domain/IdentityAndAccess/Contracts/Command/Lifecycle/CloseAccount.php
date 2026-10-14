<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Lifecycle;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\AccountClosure;

final readonly class CloseAccount extends IdentityCommand
{
    public function __construct(string $operationId, private AccountClosure $input)
    {
        parent::__construct($operationId);
    }

    public function input(): AccountClosure
    {
        return $this->input;
    }
}
