<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\QueryHandlers;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetActorHistory;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ActorHistory;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;
use Backendbase\Shared\Exception\ResourceNotFound;

/** @implements QueryHandler<GetActorHistory, ActorHistory> */
final readonly class GetActorHistoryHandler implements QueryHandler
{
    public function __construct(private AuthenticationGate $gate, private IdentityReadRepository $records)
    {
    }

    public function handle(Query $query): ActorHistory
    {
        $principal = $query->principal();
        $principal->assertAdmin();
        $gate = $this->gate;
        $gate->confirm($principal);
        $records = $this->records;

        return $records->actorHistory($query->realm(), $query->actorId()) ?? throw ResourceNotFound::create('The actor history was not found.');
    }
}
