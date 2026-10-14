<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Operations;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;

final readonly class ApplyOperatorAction extends IdentityCommand
{
    public function __construct(string $operationId, private OperatorInput $input, private bool $apply)
    {
        parent::__construct($operationId);
    }

    public function input(): OperatorInput
    {
        return $this->input;
    }

    public function applies(): bool
    {
        return $this->apply;
    }
}
