<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Contacts\ContactAttempt;

final readonly class VerifyContact extends IdentityCommand
{
    public function __construct(string $operationId, private ContactAttempt $input)
    {
        parent::__construct($operationId);
    }

    public function input(): ContactAttempt
    {
        return $this->input;
    }
}
