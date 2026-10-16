<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ContactRoutePolicy implements MiddlewareInterface
{
    /** @param array{action: string, authenticated: bool} $policy */
    public function __construct(private array $policy)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute('contactAction', $this->policy['action']);
        $request = $request->withAttribute('contactAuthenticated', $this->policy['authenticated']);

        return $handler->handle($request);
    }
}
