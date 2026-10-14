<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\Exception\InvalidUserInput;

use function in_array;

final readonly class ActorChangeInput
{
    /** @var array{actorId: IdentityId, realm: Realm, action: string} */
    private array $values;

    public function __construct(string $actorId, Realm $realm, string $action)
    {
        if (! in_array($action, ['remove', 'restore'], true)) {
            throw InvalidUserInput::create('The actor action must be remove or restore.');
        }

        $this->values = ['actorId' => IdentityId::fromString($actorId), 'realm' => $realm, 'action' => $action];
    }

    public function actorId(): string
    {
        $id = $this->values['actorId'];

        return $id->toString();
    }

    public function realm(): Realm
    {
        return $this->values['realm'];
    }

    public function action(): string
    {
        return $this->values['action'];
    }
}
