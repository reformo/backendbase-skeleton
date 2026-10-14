<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Preflight
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response->withStatus(204);
    }
}
