<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root;

use Backendbase\Shared\Http\Actions\Action;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;

class NotFound extends Action
{
    #[Override]
    protected function action(): Response
    {
        if ($this->request->getMethod() === 'OPTIONS') {
            return new EmptyResponse();
        }

        return new JsonResponse(
            [
                'code' => 'http/not-found',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => 'Endpoint does not exists',
            ],
            404,
        );
    }
}
