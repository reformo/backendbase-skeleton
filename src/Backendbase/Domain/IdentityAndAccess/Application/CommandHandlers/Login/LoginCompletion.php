<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Login;

use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use DateTimeImmutable;

final readonly class LoginCompletion
{
    public function __construct(private SessionIssuance $sessions, private IdentityOperationResults $results)
    {
    }

    public function complete(string $operationId, Principal $principal, DateTimeImmutable $expiresAt): void
    {
        $currentSessions = $this->sessions;
        $currentResults  = $this->results;

        $token = $currentSessions->issue($principal, $expiresAt);
        $currentResults->put($operationId, new IdentityOperationResult(200, [
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'expiresAt' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
        ]));
    }
}
