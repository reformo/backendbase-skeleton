<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\QueryHandlers;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetCurrentIdentity;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ActorHistory;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;

/** @implements QueryHandler<GetCurrentIdentity, ActorHistory> */
final readonly class GetCurrentIdentityHandler implements QueryHandler
{
    public function __construct(private AuthenticationGate $gate, private IdentityReadRepository $records)
    {
    }

    public function handle(Query $query): ActorHistory
    {
        $principal = $query->principal();
        $gate      = $this->gate;
        $gate->confirm($principal);
        $identity = $principal->identity();
        $records  = $this->records;

        $realm = $principal->realm();

        return $records->actorHistory($realm, $identity['actorId']) ?? throw AuthenticationDenied::create('Authentication is not permitted.');
    }
}
