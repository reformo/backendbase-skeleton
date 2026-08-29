<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Domain\IdentityAndAccess\Exception\InvalidCredentials;
use Backendbase\Shared\Primitives\PasswordHash;
use SensitiveParameterValue;

final readonly class AuthenticateAccount
{
    public function __construct(
        private AccountAuthenticationRepository $accountAuthenticationRepository,
        private TokenIssuer $tokenIssuer,
    ) {
    }

    public function authenticate(string $email, SensitiveParameterValue $password): string
    {
        $account = $this->accountAuthenticationRepository->findByEmail($email);
        if ($account === null || ! PasswordHash::create($account->passwordHash())->verifyHash($password)) {
            throw InvalidCredentials::create('The email or password is invalid.');
        }

        return $this->tokenIssuer->issueNewToken('userId', $account->uuid(), [
            'uuid' => $account->uuid(),
            'email' => $account->email(),
            'privileges' => $account->privileges(),
        ]);
    }
}
