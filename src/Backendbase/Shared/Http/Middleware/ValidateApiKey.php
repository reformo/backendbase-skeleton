<?php

declare(strict_types=1);

namespace Backendbase\Shared\Http\Middleware;

use Backendbase\Shared\Configuration\ApiKeySettings;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

use function is_string;
use function str_starts_with;

readonly class ValidateApiKey implements Middleware
{
    public function __construct(private ApiKeySettings $settings)
    {
    }

    #[Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new EmptyResponse(200);
        }

        $uriPath = $request->getUri()->getPath();
        if (
            str_starts_with($uriPath, '/webhook/')
            || str_starts_with($uriPath, '/payment-page/')
            || str_starts_with($uriPath, '/platform/agreements/')
            || str_starts_with($uriPath, '/payment/successful')
            || str_starts_with($uriPath, '/_status')
            || str_starts_with($uriPath, '/payment/failed')
            || str_starts_with($uriPath, '/platform/lookup-table')
        ) {
            return $handler->handle($request);
        }

        $apiKeyHeaderName = $request->getAttribute('apiKeyHeaderName');
        $requestApiKey    = $request->getHeaderLine($apiKeyHeaderName);
        if (empty($requestApiKey) && $request->getAttribute('useIdentifierAsApiKey')) {
            return new JsonResponse([
                'code' => 'identity-and-access/api-key-not-found',
                'title' => 'Api Key Not Validated',
                'detail' => 'Invalid Api Key',
            ], 400);
        }

        if ($request->getAttribute('useIdentifierAsApiKey')) {
            return $handler->handle($request);
        }

        $apiName = $request->getAttribute('apiName');
        if (! is_string($apiName) || ! $this->settings->accepts($apiName, $requestApiKey)) {
            return new JsonResponse([
                'code' => 'identity-and-access/api-key-not-found',
                'title' => 'Api Key Not Validated',
                'detail' => 'Invalid Api Key',
            ], 400);
        }

        return $handler->handle($request);
    }
}
