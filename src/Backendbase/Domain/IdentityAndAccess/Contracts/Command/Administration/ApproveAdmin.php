<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Administration;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AdminApprovalInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AuthenticatedInput;

final readonly class ApproveAdmin extends IdentityCommand
{
    /** @param AuthenticatedInput<AdminApprovalInput> $input */
    public function __construct(string $operationId, private AuthenticatedInput $input)
    {
        parent::__construct($operationId);
    }

    /** @return AuthenticatedInput<AdminApprovalInput> */
    public function input(): AuthenticatedInput
    {
        return $this->input;
    }
}
