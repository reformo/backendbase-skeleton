<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Http;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RoutePolicy implements MiddlewareInterface
{
    public function __construct(private Realm $realm, private string $action = '')
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute('expectedRealm', $this->realm);
        $request = $request->withAttribute('identityAction', $this->action);

        return $handler->handle($request);
    }
}
