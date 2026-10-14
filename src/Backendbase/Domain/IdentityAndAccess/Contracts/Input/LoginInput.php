<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;

final readonly class LoginInput
{
    private LoginName $name;
    private Password $secret;

    public function __construct(string $loginName, string $password)
    {
        $this->name   = new LoginName($loginName);
        $this->secret = new Password($password);
    }

    public function loginName(): LoginName
    {
        return $this->name;
    }

    public function password(): Password
    {
        return $this->secret;
    }
}
