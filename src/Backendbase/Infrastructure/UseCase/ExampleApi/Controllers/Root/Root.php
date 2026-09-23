<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root;

use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class Root extends Action
{
    public function __construct(
        private readonly ApplicationRuntimeSettings $settings,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    #[Override]
    protected function action(): Response
    {
        $cdnBaseUrl = $this->settings->cdnBaseUrl();

        $this->logger->info('Root endpoint called');

        return new JsonResponse([
            'backendbase-api' => [
                'version' => '1.0.0',
                'buildId' => '0.0.1', // Version::short(),
                'cdnBaseUrl' => $cdnBaseUrl,
            ],
        ], 200);
    }
}
