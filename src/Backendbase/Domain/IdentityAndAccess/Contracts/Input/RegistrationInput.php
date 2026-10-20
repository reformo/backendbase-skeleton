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
    private AccountNames $accountNames;
    private AccountEmail $accountEmail;
    private AccountMobile $accountMobile;
    private Password $secret;
    private VerificationContextToken|null $token;

    public function __construct(string $email, string $mobile, string $password, string|null $verificationContextToken = null, string|null $firstName = null, string|null $lastName = null)
    {
        $this->accountNames  = new AccountNames($firstName, $lastName);
        $this->accountEmail  = new AccountEmail($email);
        $this->accountMobile = new AccountMobile($mobile);
        $this->secret        = new Password($password);
        $this->token         = $verificationContextToken === null ? null : new VerificationContextToken($verificationContextToken);
    }

    public function verificationContextToken(): VerificationContextToken|null
    {
        return $this->token;
    }

    public function names(): AccountNames
    {
        return $this->accountNames;
    }

    public function email(): AccountEmail
    {
        return $this->accountEmail;
    }

    public function mobile(): AccountMobile
    {
        return $this->accountMobile;
    }

    public function password(): Password
    {
        return $this->secret;
    }
}
