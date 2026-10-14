<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Administration;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\ActorChangeInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AuthenticatedInput;
use DateTimeImmutable;

final readonly class ChangeActor extends IdentityCommand
{
    /** @param AuthenticatedInput<ActorChangeInput> $input */
    public function __construct(string $operationId, private AuthenticatedInput $input, private DateTimeImmutable $now)
    {
        parent::__construct($operationId);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /** @return AuthenticatedInput<ActorChangeInput> */
    public function input(): AuthenticatedInput
    {
        return $this->input;
    }
}
