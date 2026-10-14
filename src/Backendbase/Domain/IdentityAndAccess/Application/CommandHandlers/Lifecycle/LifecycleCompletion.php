<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\CommandHandlers\Lifecycle;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTokens;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityOperationResults;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\IdentityOperationResult;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;

final readonly class LifecycleCompletion
{
    public function __construct(private AuthenticationTokens $tokens, private IdentityOperationResults $results)
    {
    }

    public function logout(string $operationId, Principal $principal): void
    {
        $currentTokens  = $this->tokens;
        $currentResults = $this->results;

        $currentTokens->revokeSession($principal);
        $currentResults->put($operationId, new IdentityOperationResult(200, ['completed' => true]));
    }

    public function tokenRevoked(string $operationId, Principal $principal): void
    {
        $currentTokens  = $this->tokens;
        $currentResults = $this->results;

        $currentTokens->revokeToken($principal);
        $currentResults->put($operationId, new IdentityOperationResult(200, ['completed' => true]));
    }

    public function accountClosed(string $operationId, Principal $principal): void
    {
        $currentTokens  = $this->tokens;
        $currentResults = $this->results;

        $identity = $principal->identity();
        $currentTokens->revokeAccount($identity['accountId']);
        $currentResults->put($operationId, new IdentityOperationResult(200, ['completed' => true]));
    }
}
