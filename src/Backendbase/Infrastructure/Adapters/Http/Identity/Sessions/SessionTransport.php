<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Backendbase\Shared\Exception\InvalidUserInput;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpForbiddenException;

use function array_key_exists;
use function explode;
use function is_string;
use function strtolower;

final readonly class SessionTransport
{
    public function __construct(private HttpHeaderSettings $headers)
    {
    }

    public function login(ServerRequestInterface $request): string
    {
        $this->assertJson($request);
        $payload = RequestObjectInput::fromRequest($request, ['identifier', 'password', 'refreshTokenTransport']);

        return $this->mode($request, $payload);
    }

    /** @return array{RefreshToken, string} */
    public function renewal(ServerRequestInterface $request, Realm $realm): array
    {
        $this->assertJson($request);
        $payload = RequestObjectInput::fromRequest($request, ['refreshToken', 'refreshTokenTransport']);
        $mode    = $this->mode($request, $payload);
        if ($mode === 'cookie') {
            return [$this->cookieToken($request, $realm, $payload), $mode];
        }

        return [self::token($payload['refreshToken'] ?? null), $mode];
    }

    /** @param array<string, mixed> $payload */
    private function mode(ServerRequestInterface $request, array $payload): string
    {
        $payload += ['refreshTokenTransport' => 'body'];
        $mode     = $payload['refreshTokenTransport'];
        if (! is_string($mode) || ($mode !== 'body' && $mode !== 'cookie')) {
            throw InvalidUserInput::create('The refreshTokenTransport must be body or cookie.');
        }

        if ($mode === 'cookie') {
            $this->assertOrigin($request);
        }

        return $mode;
    }

    private function assertOrigin(ServerRequestInterface $request): void
    {
        $headers = $this->headers;
        $origin  = $request->getHeaderLine('Origin');
        if ($origin === '' || $origin === 'null' || ! $headers->allowsOrigin($origin)) {
            throw new HttpForbiddenException($request, 'A configured browser Origin is required for cookie authentication.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function cookieToken(ServerRequestInterface $request, Realm $realm, array $payload): RefreshToken
    {
        if (array_key_exists('refreshToken', $payload)) {
            throw InvalidUserInput::create('Cookie renewal does not accept a body refresh token.');
        }

        $cookies = $request->getCookieParams();
        $value   = $cookies[SessionCookies::name($realm)] ?? null;
        if (! is_string($value)) {
            throw AuthenticationDenied::create('The refresh session credential is missing or expired.');
        }

        return new RefreshToken($value);
    }

    private function assertJson(ServerRequestInterface $request): void
    {
        $contentType = strtolower(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]);
        if ($contentType !== 'application/json') {
            throw InvalidUserInput::create('The request body must use application/json.');
        }
    }

    private static function token(mixed $value): RefreshToken
    {
        if (! is_string($value)) {
            throw InvalidUserInput::create('A refresh token is required.');
        }

        return new RefreshToken($value);
    }
}
