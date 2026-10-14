<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Middleware;

use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Shared\Domain\Exception\DomainException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteContext;
use Throwable;

use function hrtime;

final readonly class MeasureRequest implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger, private DomainErrorProblemDetailsMapper $errors)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $started = hrtime(true);
        $status  = 500;
        try {
            $response = $handler->handle($request);
            $status   = $response->getStatusCode();

            return $response;
        } catch (Throwable $exception) {
            $status = $this->status($exception);

            throw $exception;
        } finally {
            $route  = $request->getAttribute(RouteContext::ROUTE);
            $logger = $this->logger;
            $logger->info('http.request.completed', [
                'route' => $route instanceof RouteInterface ? $route->getPattern() : 'unmatched',
                'method' => $request->getMethod(),
                'status' => $status,
                'durationMilliseconds' => (hrtime(true) - $started) / 1000000,
            ]);
        }
    }

    private function status(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getCode();
        }

        if ($exception instanceof DomainException) {
            $errors = $this->errors;
            $error  = $errors->map($exception);

            return $error->status();
        }

        return 500;
    }
}
