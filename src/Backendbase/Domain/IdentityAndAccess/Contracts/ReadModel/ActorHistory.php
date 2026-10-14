<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel;

use JsonSerializable;

final readonly class ActorHistory implements JsonSerializable
{
    /** @param array{actorId: string, accountId: string, realm: string, loginName: string, approved: bool, createdAt: string, deletedAt: string|null} $data */
    public function __construct(private array $data)
    {
    }

    /** @return array{actorId: string, accountId: string, realm: string, loginName: string, approved: bool, createdAt: string, deletedAt: string|null} */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
