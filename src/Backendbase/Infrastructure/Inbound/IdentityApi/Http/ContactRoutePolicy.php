<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Http;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ContactRoutePolicy implements MiddlewareInterface
{
    /** @param array{action: string, authenticated: bool} $policy */
    public function __construct(private Realm $realm, private array $policy)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute('expectedRealm', $this->realm);
        $request = $request->withAttribute('contactAction', $this->policy['action']);
        $request = $request->withAttribute('contactAuthenticated', $this->policy['authenticated']);

        return $handler->handle($request);
    }
}
