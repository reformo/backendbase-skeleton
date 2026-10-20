<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\Credentials;
use Backendbase\Domain\IdentityAndAccess\Application\Registration\RegistrationCompletion;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;

final readonly class RegistrationWorkflow
{
    public function __construct(private Credentials $credentials, private RegistrationCompletion $completion)
    {
    }

    /** @return array{accountId: string, actorId: string} */
    public function register(string $operationId, RegistrationInput $input): array
    {
        $completion  = $this->completion;
        $credentials = $this->credentials;

        $accountId = IdentityId::create();
        $actorId   = IdentityId::create();
        $password  = $input->password();
        $hash      = $credentials->hashPassword($password);
        $completion->complete($credentials, $input, $accountId, $actorId, $hash);

        return ['accountId' => $accountId->toString(), 'actorId' => $actorId->toString()];
    }
}
