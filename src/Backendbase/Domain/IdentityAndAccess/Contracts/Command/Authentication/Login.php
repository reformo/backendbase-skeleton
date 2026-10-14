<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RealmLogin;

final readonly class Login extends IdentityCommand
{
    public function __construct(string $operationId, private RealmLogin $input)
    {
        parent::__construct($operationId);
    }

    public function input(): RealmLogin
    {
        return $this->input;
    }
}
