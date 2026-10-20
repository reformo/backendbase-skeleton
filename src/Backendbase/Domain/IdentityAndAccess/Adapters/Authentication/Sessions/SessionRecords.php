<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class SessionRecords
{
    /** @return array{string, string, string} */
    public static function keys(Principal $principal): array
    {
        $identity = $principal->identity();
        $realm    = $principal->realm();
        $prefix   = self::prefix($realm, $identity['accountId']);

        return [$prefix . 'jti:' . $identity['jti'], $prefix . 'sid:' . $identity['sid'], $prefix . 'blocked'];
    }

    /** @return array{string, string, string} */
    public static function refreshKeys(Realm $realm, RefreshToken $token): array
    {
        $prefix    = self::prefix($realm, $token->accountId());
        $sessionId = $token->sessionId();

        return [$prefix . 'sid:' . $sessionId, $prefix . 'used-refresh:' . $sessionId . ':' . $token->digest(), $prefix . 'blocked'];
    }

    /** @return array{string, string} */
    public static function legacy(Principal $principal): array
    {
        $claims = $principal->claims();
        $token  = json_encode($claims, JSON_THROW_ON_ERROR);
        unset($claims['jti']);

        return [$token, json_encode($claims, JSON_THROW_ON_ERROR)];
    }

    private static function prefix(Realm $realm, string $accountId): string
    {
        return 'identity:{' . $realm->value . ':' . $accountId . '}:';
    }
}
