<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Middleware;

use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
use Backendbase\Shared\Authorization\AccessControl;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Throwable;

use function explode;

final readonly class AuthorizationMiddleware implements Middleware
{
    public function __construct(private TokenValidator $tokenValidator)
    {
    }

    #[Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        $accessToken = self::accessToken($request);
        if ($accessToken === null) {
            return self::authorizationFailure();
        }

        try {
            $tokenData = $this->tokenValidator->validateToken($accessToken);
        } catch (Throwable) {
            return self::authorizationFailure();
        }

        $authorizationData = AuthorizationRequestData::fromToken($tokenData);
        if ($authorizationData === null) {
            return self::authorizationFailure();
        }

        $timezone = AuthorizationRequestData::timezone($request->getHeaderLine('The-Timezone-IANA'));
        if ($timezone === null) {
            return self::timezoneFailure();
        }

        $accessControl = new Acl($authorizationData['privileges']);
        $request       = $request->withAttribute('authorizedUserId', $authorizationData['accountId']->toString())
            ->withAttribute('authorizedUserData', $authorizationData['user'])
            ->withAttribute('clientTimezone', $timezone)
            ->withAttribute(Acl::class, $accessControl)
            ->withAttribute(AccessControl::class, $accessControl);

        return $handler->handle($request);
    }

    private static function accessToken(Request $request): string|null
    {
        $parts       = explode(' ', $request->getHeaderLine('Authorization'), 2);
        $scheme      = $parts[0];
        $accessToken = $parts[1] ?? '';
        if ($scheme !== 'Bearer' || $accessToken === '') {
            return null;
        }

        return $accessToken;
    }

    private static function authorizationFailure(): JsonResponse
    {
        return self::problem(
            401,
            'identity-access/authorization-expired',
            'Authorization Expired',
            'The bearer token is missing or invalid.',
        );
    }

    private static function timezoneFailure(): JsonResponse
    {
        return self::problem(
            400,
            'general/invalid-user-input',
            'Invalid user input provided',
            'The The-Timezone-IANA header must contain a valid timezone.',
        );
    }

    private static function problem(int $status, string $code, string $title, string $detail): JsonResponse
    {
        return new JsonResponse([
            'type' => 'about:blank',
            'code' => $code,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
