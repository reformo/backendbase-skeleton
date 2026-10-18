<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Domain\Profiles\AccountNames;

final readonly class RegistrationInput
{
    /** @var array{names: AccountNames, email: AccountEmail, mobile: AccountMobile, password: Password, token: VerificationContextToken} */
    private array $values;

    public function __construct(string $email, string $mobile, string $password, string $verificationContextToken, string|null $firstName = null, string|null $lastName = null)
    {
        $this->values = ['names' => new AccountNames($firstName, $lastName), 'email' => new AccountEmail($email), 'mobile' => new AccountMobile($mobile), 'password' => new Password($password), 'token' => new VerificationContextToken($verificationContextToken)];
    }

    public function verificationContextToken(): VerificationContextToken
    {
        return $this->values['token'];
    }

    public function names(): AccountNames
    {
        return $this->values['names'];
    }

    public function email(): AccountEmail
    {
        return $this->values['email'];
    }

    public function mobile(): AccountMobile
    {
        return $this->values['mobile'];
    }

    public function password(): Password
    {
        return $this->values['password'];
    }
}
