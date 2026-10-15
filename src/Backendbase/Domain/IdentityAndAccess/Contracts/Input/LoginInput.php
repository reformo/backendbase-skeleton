<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginIdentifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;

final readonly class LoginInput
{
    private LoginIdentifier $identifier;
    private Password $secret;

    public function __construct(string $identifier, string $password)
    {
        $this->identifier = new LoginIdentifier($identifier);
        $this->secret     = new Password($password);
    }

    public function identifier(): LoginIdentifier
    {
        return $this->identifier;
    }

    public function password(): Password
    {
        return $this->secret;
    }
}
