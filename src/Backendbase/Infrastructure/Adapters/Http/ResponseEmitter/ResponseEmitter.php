<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\ResponseEmitter;

use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Override;
use Psr\Http\Message\ResponseInterface;
use Slim\ResponseEmitter as SlimResponseEmitter;

use function array_key_exists;
use function is_string;
use function ob_clean;
use function ob_get_contents;

class ResponseEmitter extends SlimResponseEmitter
{
    public function __construct(
        private readonly HttpHeaderSettings $settings,
        int $responseChunkSize = 4096,
    ) {
        parent::__construct($responseChunkSize);
    }

    #[Override]
    public function emit(ResponseInterface $response): void
    {
        $serverOrigin   = array_key_exists('HTTP_ORIGIN', $_SERVER) ? $_SERVER['HTTP_ORIGIN'] : null;
        $requestOrigin  = is_string($serverOrigin) ? $serverOrigin : null;
        $allowedOrigin  = $this->settings->allowedOrigin($requestOrigin);
        $allowedHeaders = $this->settings->allowedHeaders();

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader(
                'Access-Control-Allow-Headers',
                $allowedHeaders,
            )
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');

        if (ob_get_contents()) {
            ob_clean();
        }

        parent::emit($response);
    }
}
