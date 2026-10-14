<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Shared\Exception\InvalidUserInput;

use function preg_match;
use function strlen;
use function trim;

final readonly class AdminApprovalInput
{
    private IdentityId $id;

    public function __construct(string $actorId, private string $evidence)
    {
        $this->id = IdentityId::fromString($actorId);
        if (trim($evidence) === '' || strlen($evidence) > 160 || preg_match('/[\x00-\x1f\x7f]/', $evidence) === 1) {
            throw InvalidUserInput::create('A bounded approval evidence reference is required.');
        }
    }

    public function actorId(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function evidence(): string
    {
        return $this->evidence;
    }
}
