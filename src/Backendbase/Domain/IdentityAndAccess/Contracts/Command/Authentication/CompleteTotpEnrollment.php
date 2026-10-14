<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RealmProof;

final readonly class CompleteTotpEnrollment extends IdentityCommand
{
    public function __construct(string $operationId, private RealmProof $input)
    {
        parent::__construct($operationId);
    }

    public function input(): RealmProof
    {
        return $this->input;
    }
}
