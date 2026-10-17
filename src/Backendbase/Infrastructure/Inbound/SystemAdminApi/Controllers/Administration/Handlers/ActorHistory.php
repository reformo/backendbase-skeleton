<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Administration\Handlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetActorHistory;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Adapters\Http\Actions\ActionPayload;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Exception\InvalidUserInput;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use function is_string;

final class ActorHistory extends Action
{
    public function __construct(LoggerInterface $logger, private QueryBus $queries)
    {
        parent::__construct($logger);
    }

    protected function action(): ResponseInterface
    {
        $request   = $this->request;
        $principal = $request->getAttribute('identityPrincipal');
        if (! $principal instanceof Principal) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $arguments  = $this->routeArguments;
        $realmValue = $arguments['realm'] ?? null;
        $realm      = is_string($realmValue) ? Realm::tryFrom($realmValue) : null;
        if ($realm === null) {
            throw InvalidUserInput::create('The actor realm is invalid.');
        }

        $actorId = $arguments['actor-id'] ?? null;
        if (! is_string($actorId)) {
            throw InvalidUserInput::create('The actor identifier is invalid.');
        }

        $queries = $this->queries;
        $history = $queries->handle(new GetActorHistory($principal, $realm, $actorId));

        $payload = new ActionPayload(200, ['actor' => $history]);

        return new JsonResponse($payload, 200, ['Cache-Control' => 'no-store']);
    }
}
