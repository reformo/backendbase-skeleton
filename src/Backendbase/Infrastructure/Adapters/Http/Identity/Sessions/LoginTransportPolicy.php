<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity\Sessions;

use Backendbase\Infrastructure\Adapters\Http\Idempotency\ReplayInput;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

final readonly class LoginTransportPolicy implements MiddlewareInterface
{
    public function __construct(private SessionTransport $transport)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = RouteContext::fromRequest($request);
        $route   = $context->getRoute();
        if ($route?->getPattern() !== '/auth/login') {
            return $handler->handle($request);
        }

        ReplayInput::key($request);
        $transport = $this->transport;
        $mode      = $transport->login($request);

        $request = $request->withAttribute('sessionTransport', $mode);

        return $handler->handle($request);
    }
}
