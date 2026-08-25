<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

final readonly class Liveness
{
    public function __invoke(): ResponseInterface
    {
        return new JsonResponse(['status' => 'alive'], 200);
    }
}
