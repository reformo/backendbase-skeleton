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
    /** @var array{accountId: IdentityId, evidence: string} */
    private array $values;

    public function __construct(string $accountId, string $evidence)
    {
        if (trim($evidence) === '' || strlen($evidence) > 160 || preg_match('/[\x00-\x1f\x7f]/', $evidence) === 1) {
            throw InvalidUserInput::create('A bounded invitation evidence reference is required.');
        }

        $this->values = ['accountId' => IdentityId::fromString($accountId), 'evidence' => $evidence];
    }

    public function accountId(): string
    {
        $id = $this->values['accountId'];

        return $id->toString();
    }

    public function evidence(): string
    {
        return $this->values['evidence'];
    }
}
