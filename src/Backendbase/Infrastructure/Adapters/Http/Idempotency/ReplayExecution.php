<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Idempotency;

use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final readonly class ReplayExecution
{
    public function __construct(private HttpErrorHandler $errors)
    {
    }

    public function run(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $errors = $this->errors;

            return $errors($request, $exception, false, true, false);
        }
    }
}
