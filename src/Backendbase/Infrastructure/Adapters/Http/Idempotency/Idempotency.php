<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Idempotency;

use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Shared\Idempotency\RequestMismatch;
use Backendbase\Shared\Idempotency\RequestOutstanding;
use Backendbase\Shared\Idempotency\RequestReplayStore;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Throwable;

use function in_array;

final readonly class Idempotency implements MiddlewareInterface
{
    public function __construct(private RequestReplayStore $store, private ReplayExchange $exchange)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $handler->handle($request);
        }

        $key = ReplayInput::key($request);
        RequestObjectInput::boundedBody($request);
        try {
            return $this->processWrite($request, $handler, $key);
        } catch (RequestMismatch) {
            return self::problem(422, 'Unprocessable Content', 'idempotency/request-mismatch', 'This Idempotency-Key was used with different request content.');
        } catch (RequestOutstanding) {
            return self::problem(409, 'Conflict', 'idempotency/request-outstanding', 'This request is in progress or its outcome requires reconciliation.');
        } catch (Throwable $exception) {
            throw new HttpException($request, 'Idempotency storage is unavailable. Retry with the same key.', 503, $exception);
        }
    }

    private function processWrite(ServerRequestInterface $request, RequestHandlerInterface $handler, string $key): ResponseInterface
    {
        $exchange = $this->exchange;
        $store    = $this->store;
        $identity = $exchange->identity($request, $key);
        $stored   = $store->claim($identity);
        if ($stored !== null) {
            return $exchange->replay($identity, $stored);
        }

        $response  = $exchange->execute($request, $handler);
        $encrypted = $exchange->encrypt($identity, $response);
        $store->complete($identity, $encrypted);

        return $response;
    }

    private static function problem(int $status, string $title, string $code, string $detail): ResponseInterface
    {
        return new JsonResponse([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store']);
    }
}
