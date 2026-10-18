<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Idempotency;

use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Idempotency\RequestReplayCipher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ReplayExchange
{
    public function __construct(private RequestReplayCipher $cipher, private ReplayExecution $execution)
    {
    }

    public function identity(ServerRequestInterface $request, string $key): RequestIdentity
    {
        return ReplayInput::identity($request, $key, $this->cipher);
    }

    public function replay(RequestIdentity $identity, string $response): ResponseInterface
    {
        $cipher = $this->cipher;
        $plain  = $cipher->decrypt($identity, $response);

        return ReplayResponse::decode($plain);
    }

    public function encrypt(RequestIdentity $identity, ResponseInterface $response): string
    {
        $cipher = $this->cipher;
        $plain  = ReplayResponse::encode($response);

        return $cipher->encrypt($identity, $plain);
    }

    public function execute(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $execution = $this->execution;

        return $execution->run($request, $handler);
    }
}
