<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Http;

use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Shared\Authorization\AccessControl;
use DateTimeImmutable;
use DateTimeZone;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Throwable;

use function array_key_exists;
use function array_walk;
use function explode;

use const DATE_ATOM;

readonly class AuthorizationMiddleware implements Middleware
{
    public function __construct(private TokenValidator $tokenValidator)
    {
    }

    #[Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authorizationHeader = $request->getHeaderLine('Authorization');

        $parts       = explode(' ', $authorizationHeader, 2);
        $scheme      = $parts[0];
        $accessToken = $parts[1] ?? '';

        if ($scheme !== 'Bearer' || $accessToken === '') {
            return new JsonResponse([
                'code' => 'identity-and-access/authorization-header-not-found',
                'title' => 'Authorization failed',
                'detail' => 'Invalid Authorization Header',
                'expectedHeader' => 'Bearer <accessToken>',
            ], 400);
        }

        try {
            $tokenData = $this->tokenValidator->validateToken($accessToken);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'code' => $exception->getCode(),
                'title' => 'Authorization failed',
                'detail' => $exception->getMessage(),
            ], 400);
        }

        if (! array_key_exists('user', $tokenData)) {
            throw AuthorizationExpired::create('Invalid token');
        }

        $userData   = $tokenData['user'];
        $privileges = $tokenData['privileges'] ?? [];

        array_walk($userData, static function (&$value): void {
            if (! ($value instanceof DateTimeImmutable)) {
                return;
            }

            $value = $value->format(DATE_ATOM);
        });

        $timezone = $request->getHeaderLine('The-Timezone-IANA');
        if (empty($timezone)) {
            $timezone = 'UTC';
        }

        $accessControl = new Acl($privileges);
        $request       = $request->withAttribute('authorizedUserId', $userData['uuid'])
            ->withAttribute('authorizedUserData', $userData)
            ->withAttribute('clientTimezone', new DateTimeZone($timezone))
            ->withAttribute(Acl::class, $accessControl)
            ->withAttribute(AccessControl::class, $accessControl);

        return $handler->handle($request);
    }
}
