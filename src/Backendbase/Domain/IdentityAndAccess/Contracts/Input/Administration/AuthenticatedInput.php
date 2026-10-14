<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;

/** @template TInput of object */
final readonly class AuthenticatedInput
{
    /** @param TInput $input */
    public function __construct(private Principal $principal, private object $input)
    {
    }

    public function principal(): Principal
    {
        return $this->principal;
    }

    /** @return TInput */
    public function input(): object
    {
        return $this->input;
    }
}
