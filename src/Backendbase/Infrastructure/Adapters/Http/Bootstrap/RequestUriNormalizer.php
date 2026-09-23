<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Bootstrap;

use function is_string;
use function str_replace;

final class RequestUriNormalizer
{
    /**
     * @param array<string, mixed> $server
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $server): array
    {
        $scriptName = $server['SCRIPT_NAME'] ?? null;
        $requestUri = $server['REQUEST_URI'] ?? null;
        if (! is_string($scriptName) || ! is_string($requestUri) || $scriptName === '/index.php') {
            return $server;
        }

        $subFolder             = str_replace('/index.php', '', $scriptName);
        $server['REQUEST_URI'] = str_replace($subFolder, '', $requestUri);

        return $server;
    }
}
