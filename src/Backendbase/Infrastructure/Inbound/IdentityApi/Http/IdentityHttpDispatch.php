<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\IdentityApi\Http;

use Backendbase\Domain\IdentityAndAccess\Application\IdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityCommand;
use Backendbase\Infrastructure\Adapters\Http\Actions\ActionPayload;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

final readonly class IdentityHttpDispatch
{
    public function __construct(private IdentityOperation $operation)
    {
    }

    public function execute(IdentityCommand $command, string $responseKey): ResponseInterface
    {
        $operation = $this->operation;
        $result    = $operation->execute($command);

        $status  = $result->status();
        $payload = new ActionPayload($status, [$responseKey => $result->data()]);

        return new JsonResponse($payload, $status, ['Cache-Control' => 'no-store']);
    }
}
