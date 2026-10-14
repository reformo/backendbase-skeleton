<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;

final readonly class RegisterUser extends IdentityCommand
{
    public function __construct(string $operationId, private RegistrationInput $input)
    {
        parent::__construct($operationId);
    }

    public function input(): RegistrationInput
    {
        return $this->input;
    }
}
