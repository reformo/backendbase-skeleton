<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity\Sessions;

use Backendbase\Domain\IdentityAndAccess\Application\IdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function is_string;

final readonly class SessionHttpDispatch
{
    public function __construct(private IdentityOperation $operation)
    {
    }

    public function execute(IdentityCommand $command, ServerRequestInterface $request, Realm $realm): ResponseInterface
    {
        $operation = $this->operation;
        $result    = $operation->execute($command);
        $data      = $result->data();
        if ($request->getAttribute('sessionTransport') !== 'cookie') {
            return new JsonResponse(['session' => $data], $result->status(), ['Cache-Control' => 'no-store']);
        }

        $token     = $data['refreshToken'] ?? null;
        $expiresAt = $data['sessionExpiresAt'] ?? null;
        if (! is_string($token) || ! is_string($expiresAt)) {
            throw new RuntimeException('The session result is invalid.');
        }

        unset($data['refreshToken']);
        $response = new JsonResponse(['session' => $data], $result->status(), ['Cache-Control' => 'no-store']);

        return SessionCookies::issue($response, $realm, $token, $expiresAt);
    }
}
