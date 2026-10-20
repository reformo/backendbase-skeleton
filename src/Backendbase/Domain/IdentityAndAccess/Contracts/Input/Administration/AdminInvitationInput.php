<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Shared\Exception\InvalidUserInput;

use function preg_match;
use function strlen;
use function trim;

final readonly class AdminInvitationInput
{
    private IdentityId $id;

    public function __construct(string $accountId, private string $evidence)
    {
        if (trim($evidence) === '' || strlen($evidence) > 160 || preg_match('/[\x00-\x1f\x7f]/', $evidence) === 1) {
            throw InvalidUserInput::create('A bounded invitation evidence reference is required.');
        }

        $this->id = IdentityId::fromString($accountId);
    }

    public function accountId(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function evidence(): string
    {
        return $this->evidence;
    }
}
