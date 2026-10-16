<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\System\Handlers;

use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Shared\Health\ReadinessChecks;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class Readiness extends Action
{
    public function __construct(LoggerInterface $logger, private ReadinessChecks $checks)
    {
        parent::__construct($logger);
    }

    protected function action(): ResponseInterface
    {
        $checks = $this->checks;
        $report = $checks->run();

        return new JsonResponse($report, $report->isReady() ? 200 : 503);
    }
}
