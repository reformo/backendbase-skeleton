<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTools;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use DateTimeImmutable;

final readonly class RegistrationPreparation
{
    public function __construct(private AuthenticationTools $tools, private RegistrationEvidence $evidence)
    {
    }

    public function hashPassword(Password $password): string
    {
        $tools = $this->tools;

        return $tools->hashPassword($password);
    }

    public function initializeCredentials(string $accountId, string $hash): void
    {
        $tools = $this->tools;
        $tools->initializeCredentials($accountId, $hash);
    }

    /** @return array{list<ContactInfo>, DateTimeImmutable} */
    public function verify(RegistrationInput $input): array
    {
        $evidence = $this->evidence;

        return $evidence->verify($input);
    }

    /** @param list<ContactInfo> $contacts */
    public function consume(array $contacts, string $accountId, string $actorId, DateTimeImmutable $now): void
    {
        $evidence = $this->evidence;
        $evidence->consume($contacts, $accountId, $actorId, $now);
    }
}
