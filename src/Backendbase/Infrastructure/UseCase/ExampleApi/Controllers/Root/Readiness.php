<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root;

use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Shared\Health\ReadinessChecks;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class Readiness extends Action
{
    public function __construct(private readonly ReadinessChecks $checks, LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): ResponseInterface
    {
        $report = $this->checks->run();
        if ($report->isReady()) {
            return new JsonResponse($report, 200);
        }

        $this->logger->warning('One or more readiness checks failed.');

        return new JsonResponse($report, 503);
    }
}
