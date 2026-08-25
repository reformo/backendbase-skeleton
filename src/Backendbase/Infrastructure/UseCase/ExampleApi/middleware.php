<?php

declare(strict_types=1);

use Backendbase\Shared\Http\Middleware\ValidateApiKey;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RKA\Middleware\IpAddress;
use Slim\App;

return static function (App $app): void {
    $headersToInspect = [
        'X-Client-Ip',
        'X-Client-IP',
        'CF-Connecting-IP',
        'True-Client-IP',
        'X-Real-IP',
        'Forwarded',
        'X-Forwarded-For',
        'X-Forwarded',
        'X-Cluster-Client-Ip',
        'Client-Ip',
    ];

    /**
     * Data and validation with Request middlewares
     */

    $app->add(new IpAddress(attributeName: 'ClientIp', headersToInspect: $headersToInspect));
    $app->add(ValidateApiKey::class);
    // Keep this middleware at the bottom
    $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) { // phpcs:ignore
        $request = $request
            ->withAttribute('useIdentifierAsApiKey', false)
            ->withAttribute('apiName', 'example-api')
            ->withAttribute('apiKeyHeaderName', 'Backendbase-Api-Key');

        return $handler->handle($request);
    });
};
