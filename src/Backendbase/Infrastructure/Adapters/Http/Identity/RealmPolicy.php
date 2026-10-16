<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RealmPolicy implements MiddlewareInterface
{
    public function __construct(private Realm $realm)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $realm   = $this->realm;
        $request = $request->withAttribute('expectedRealm', $realm);

        return $handler->handle($request);
    }
}
