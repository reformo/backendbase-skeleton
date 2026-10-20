<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity\Sessions;

use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RefreshAuthentication implements MiddlewareInterface
{
    public function __construct(private SessionTransport $transport, private SessionIssuance $sessions)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $realm = $request->getAttribute('expectedRealm');
        if (! $realm instanceof Realm) {
            throw AuthenticationDenied::create('A trusted authentication realm is required.');
        }

        $transport      = $this->transport;
        [$token, $mode] = $transport->renewal($request, $realm);
        $sessions       = $this->sessions;
        $principal      = $sessions->authenticateRefresh($realm, $token);
        $request        = $request->withAttribute('identityPrincipal', $principal);
        $sessionId      = $token->sessionId();
        $request        = $request->withAttribute('refreshSessionId', $sessionId);
        $request        = $request->withAttribute('refreshCredential', $token);

        $request  = $request->withAttribute('sessionTransport', $mode);
        $response = $handler->handle($request);

        return SessionResponseGuard::confirm($response, $realm, $sessions);
    }
}
