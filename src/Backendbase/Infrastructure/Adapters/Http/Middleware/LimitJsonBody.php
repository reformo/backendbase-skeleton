<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Middleware;

use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function explode;
use function strtolower;

final readonly class LimitJsonBody implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtolower(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]) === 'application/json') {
            RequestObjectInput::boundedBody($request);
        }

        return $handler->handle($request);
    }
}
