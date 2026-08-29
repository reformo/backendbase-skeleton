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
        if (! is_string($apiKeyHeaderName) || $apiKeyHeaderName === '') {
            return self::failureResponse();
        }

        $requestApiKey  = $request->getHeaderLine($apiKeyHeaderName);
        $usesIdentifier = $request->getAttribute('useIdentifierAsApiKey') === true;
        if (empty($requestApiKey) && $usesIdentifier) {
            return self::failureResponse();
        }

        if ($usesIdentifier) {
            return $handler->handle($request);
        }

        $apiName = $request->getAttribute('apiName');
        if (! is_string($apiName) || ! $this->settings->accepts($apiName, $requestApiKey)) {
            return self::failureResponse();
        }

        return $handler->handle($request);
    }

    private static function failureResponse(): JsonResponse
    {
        return new JsonResponse([
            'type' => 'about:blank',
            'code' => 'identity-access/api-key-invalid',
            'title' => 'API Key Invalid',
            'status' => 401,
            'detail' => 'The API key is missing or invalid.',
        ], 401, ['Content-Type' => 'application/problem+json']);
    }
}
