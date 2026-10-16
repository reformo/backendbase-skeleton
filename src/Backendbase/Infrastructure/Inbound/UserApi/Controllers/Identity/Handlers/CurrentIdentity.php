<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\UserApi\Controllers\Identity\Handlers;

use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetCurrentIdentity;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Adapters\Http\Actions\ActionPayload;
use Backendbase\Shared\CQRS\QueryBus;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class CurrentIdentity extends Action
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

        $queries  = $this->queries;
        $current  = $queries->handle(new GetCurrentIdentity($principal));
        $identity = $current->jsonSerialize();
        $realm    = $principal->realm();

        $payload = new ActionPayload(200, ['user' => ['accountId' => $identity['accountId'], 'actorId' => $identity['actorId'], 'realm' => $realm->value, 'email' => $identity['email'], 'mobile' => $identity['mobile'], 'firstName' => $identity['firstName'], 'lastName' => $identity['lastName']]]);

        return new JsonResponse($payload, 200, ['Cache-Control' => 'no-store']);
    }
}
