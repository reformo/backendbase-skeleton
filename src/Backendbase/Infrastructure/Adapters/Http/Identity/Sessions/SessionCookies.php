<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Identity\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;

use function gmdate;
use function strtolower;

final class SessionCookies
{
    public static function name(Realm $realm): string
    {
        return '__Host-backendbase-' . strtolower($realm->value) . '-refresh';
    }

    public static function issue(ResponseInterface $response, Realm $realm, string $token, string $expiresAt): ResponseInterface
    {
        $deadline = new DateTimeImmutable($expiresAt);
        $expires  = gmdate('D, d M Y H:i:s \G\M\T', $deadline->getTimestamp());

        return $response->withAddedHeader('Set-Cookie', self::name($realm) . '=' . $token . '; Path=/; Expires=' . $expires . '; Secure; HttpOnly; SameSite=Strict');
    }

    public static function clear(ResponseInterface $response, Realm $realm): ResponseInterface
    {
        return $response->withAddedHeader('Set-Cookie', self::name($realm) . '=; Path=/; Max-Age=0; Secure; HttpOnly; SameSite=Strict');
    }
}
