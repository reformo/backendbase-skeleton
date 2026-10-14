<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Http;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function preg_match;

final readonly class RealmAuthentication implements MiddlewareInterface
{
    public function __construct(private AuthenticationGate $gate)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $realm  = $request->getAttribute('expectedRealm');
        $header = $request->getHeaderLine('Authorization');
        if (! $realm instanceof Realm || preg_match('/\ABearer ([A-Za-z0-9._-]+)\z/iD', $header, $matches) !== 1) {
            throw AuthenticationDenied::create('Authentication is not permitted.');
        }

        $gate      = $this->gate;
        $principal = $gate->verify($realm, $matches[1]);

        return $handler->handle($request->withAttribute('identityPrincipal', $principal));
    }
}
