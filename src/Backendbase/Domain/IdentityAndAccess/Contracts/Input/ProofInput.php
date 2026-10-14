<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Shared\Exception\InvalidUserInput;

use function preg_match;

final readonly class ProofInput
{
    private IdentityId $id;

    public function __construct(string $challengeId, private string $code)
    {
        $this->id = IdentityId::fromString($challengeId);
        if (preg_match('/\A[0-9]{6}\z/D', $code) !== 1) {
            throw InvalidUserInput::create('The proof code must contain six ASCII digits.');
        }
    }

    public function challengeId(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function code(): string
    {
        return $this->code;
    }
}
