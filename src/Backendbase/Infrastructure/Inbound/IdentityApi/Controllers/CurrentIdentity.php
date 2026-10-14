<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Controllers;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Adapters\Http\Actions\ActionPayload;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

final class CurrentIdentity extends Action
{
    protected function action(): ResponseInterface
    {
        $request   = $this->request;
        $principal = $request->getAttribute('identityPrincipal');
        if (! $principal instanceof Principal) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $identity = $principal->identity();
        $realm    = $principal->realm();

        $responseKey = $realm === Realm::USER ? 'user' : 'admin';
        $payload     = new ActionPayload(200, [$responseKey => ['accountId' => $identity['accountId'], 'actorId' => $identity['actorId'], 'realm' => $realm->value]]);

        return new JsonResponse($payload, 200, ['Cache-Control' => 'no-store']);
    }
}
