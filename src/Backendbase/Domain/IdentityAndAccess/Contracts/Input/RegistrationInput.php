<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;

final readonly class RegistrationInput
{
    /** @var array{email: AccountEmail, loginName: LoginName, password: Password} */
    private array $values;

    public function __construct(string $email, string $loginName, string $password)
    {
        $this->values = ['email' => new AccountEmail($email), 'loginName' => new LoginName($loginName), 'password' => new Password($password)];
    }

    public function email(): AccountEmail
    {
        return $this->values['email'];
    }

    public function loginName(): LoginName
    {
        return $this->values['loginName'];
    }

    public function password(): Password
    {
        return $this->values['password'];
    }
}
