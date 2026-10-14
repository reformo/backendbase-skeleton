<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Administration;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AdminInvitationInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AuthenticatedInput;
use DateTimeImmutable;

final readonly class InviteAdmin extends IdentityCommand
{
    /** @param AuthenticatedInput<AdminInvitationInput> $input */
    public function __construct(string $operationId, private AuthenticatedInput $input, private DateTimeImmutable $now)
    {
        parent::__construct($operationId);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /** @return AuthenticatedInput<AdminInvitationInput> */
    public function input(): AuthenticatedInput
    {
        return $this->input;
    }
}
