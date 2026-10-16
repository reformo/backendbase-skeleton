<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\UserApi\Controllers\System\Handlers;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

final readonly class Liveness
{
    public function __invoke(): ResponseInterface
    {
        return new JsonResponse(['status' => 'alive']);
    }
}
