<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

final readonly class RealmProof
{
    public function __construct(private Realm $realm, private ProofInput $input)
    {
    }

    public function realm(): Realm
    {
        return $this->realm;
    }

    public function input(): ProofInput
    {
        return $this->input;
    }
}
