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

    public function execute(IdentityCommand $command): ResponseInterface
    {
        $operation = $this->operation;
        $result    = $operation->execute($command);

        return new JsonResponse(new ActionPayload($result->status(), $result->data()), $result->status(), ['Cache-Control' => 'no-store']);
    }
}
