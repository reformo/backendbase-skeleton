<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity\Sessions;

use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_find;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function str_starts_with;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;

final class SessionResponseGuard
{
    public static function confirm(ResponseInterface $response, Realm $realm, SessionIssuance $sessions): ResponseInterface
    {
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $token = self::token($response, $realm);
        $sessions->confirmResult($realm, new RefreshToken($token));

        return $response;
    }

    private static function token(ResponseInterface $response, Realm $realm): string
    {
        $prefix = SessionCookies::name($realm) . '=';
        $cookie = array_find($response->getHeader('Set-Cookie'), static fn (string $value): bool => str_starts_with($value, $prefix));
        if ($cookie !== null) {
            return explode(';', substr($cookie, strlen($prefix)), 2)[0];
        }

        $body    = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $session = is_array($body) ? ($body['session'] ?? null) : null;
        $token   = is_array($session) ? ($session['refreshToken'] ?? null) : null;
        if (! is_string($token)) {
            throw new RuntimeException('The session response credential is missing.');
        }

        return $token;
    }
}
