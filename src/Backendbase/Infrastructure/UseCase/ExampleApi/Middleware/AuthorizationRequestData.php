<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\UseCase\ExampleApi\Middleware;

use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

use function is_array;
use function is_scalar;
use function is_string;

use const DATE_ATOM;

final class AuthorizationRequestData
{
    /**
     * @param array<string, mixed> $tokenData
     *
     * @return array{accountId: AccountId, user: array<string, bool|float|int|string|null>, privileges: list<string>}|null
     */
    public static function fromToken(array $tokenData): array|null
    {
        $userValue = $tokenData['user'] ?? null;
        if (! is_array($userValue) || ! is_string($userValue['uuid'] ?? null)) {
            return null;
        }

        $user = self::user($userValue);
        if ($user === null) {
            return null;
        }

        $privileges = self::privileges($tokenData['privileges'] ?? null);
        if ($privileges === null) {
            return null;
        }

        $uuid = $userValue['uuid'];
        try {
            $accountId = AccountId::fromString($uuid);
        } catch (Throwable) {
            return null;
        }

        return ['accountId' => $accountId, 'user' => $user, 'privileges' => $privileges];
    }

    public static function timezone(string $timezone): DateTimeZone|null
    {
        if ($timezone === '') {
            return null;
        }

        try {
            return new DateTimeZone($timezone);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<string, bool|float|int|string|null>|null
     */
    private static function user(array $value): array|null
    {
        $user = ['uuid' => $value['uuid']];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                return null;
            }

            if ($key === 'uuid') {
                continue;
            }

            if ($item instanceof DateTimeImmutable) {
                $user[$key] = $item->format(DATE_ATOM);
                continue;
            }

            if (! is_scalar($item) && $item !== null) {
                return null;
            }

            $user[$key] = $item;
        }

        return $user;
    }

    /** @return list<string>|null */
    private static function privileges(mixed $value): array|null
    {
        if (! is_array($value)) {
            return null;
        }

        try {
            $privileges = new AccountPrivileges($value);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $privileges->slugs();
    }
}
