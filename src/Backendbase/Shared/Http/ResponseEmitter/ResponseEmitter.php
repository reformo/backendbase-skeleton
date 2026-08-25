<?php

declare(strict_types=1);

namespace Backendbase\Shared\Http\ResponseEmitter;

use Backendbase\Shared\Settings;
use Override;
use Psr\Http\Message\ResponseInterface;
use Slim\ResponseEmitter as SlimResponseEmitter;

use function array_key_exists;
use function array_map;
use function count;
use function explode;
use function in_array;
use function ob_clean;
use function ob_get_contents;

class ResponseEmitter extends SlimResponseEmitter
{
    public function __construct(
        private readonly Settings $settings,
        int $responseChunkSize = 4096,
    ) {
        parent::__construct($responseChunkSize);
    }

    #[Override]
    public function emit(ResponseInterface $response): void
    {
        $headers        = $this->settings->get('headers');
        $allowedOrigins = array_map('trim', explode(',', (string) $headers['Access-Control-Allow-Origin']));
        $origin         = null;
        if (array_key_exists('HTTP_ORIGIN', $_SERVER) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins, true)) {
            $origin = $_SERVER['HTTP_ORIGIN'];
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin ?? $allowedOrigins[count($allowedOrigins) - 1])
            ->withHeader(
                'Access-Control-Allow-Headers',
                $headers['Access-Control-Allow-Headers'],
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
