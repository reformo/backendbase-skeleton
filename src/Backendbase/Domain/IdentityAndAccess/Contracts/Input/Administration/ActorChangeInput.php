<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\Exception\InvalidUserInput;

use function in_array;

final readonly class ActorChangeInput
{
    private IdentityId $id;

    public function __construct(string $actorId, private Realm $realm, private string $action)
    {
        if (! in_array($action, ['remove', 'restore'], true)) {
            throw InvalidUserInput::create('The actor action must be remove or restore.');
        }

        $this->id = IdentityId::fromString($actorId);
    }

    public function actorId(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function realm(): Realm
    {
        return $this->realm;
    }

    public function action(): string
    {
        return $this->action;
    }
}
