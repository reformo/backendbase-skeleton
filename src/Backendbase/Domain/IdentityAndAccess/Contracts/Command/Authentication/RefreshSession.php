<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\SessionRenewal;

final readonly class RefreshSession extends IdentityCommand
{
    public function __construct(string $operationId, private SessionRenewal $input)
    {
        parent::__construct($operationId);
    }

    public function input(): SessionRenewal
    {
        return $this->input;
    }
}
