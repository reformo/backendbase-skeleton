<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Query;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ActorHistory;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\CQRS\Query;

/** @implements Query<ActorHistory> */
final readonly class GetActorHistory implements Query
{
    /** @var array{realm: Realm, actorId: IdentityId} */
    private array $target;

    public function __construct(private Principal $principal, Realm $realm, string $actorId)
    {
        $this->target = ['realm' => $realm, 'actorId' => IdentityId::fromString($actorId)];
    }

    public function principal(): Principal
    {
        return $this->principal;
    }

    public function realm(): Realm
    {
        return $this->target['realm'];
    }

    public function actorId(): string
    {
        $id = $this->target['actorId'];

        return $id->toString();
    }

    /** @return array{actorId: string, realm: string} */
    public function jsonSerialize(): array
    {
        $realm = $this->target['realm'];

        return ['actorId' => $this->actorId(), 'realm' => $realm->value];
    }
}
