<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use DateTimeImmutable;
use InvalidArgumentException;
use UnexpectedValueException;

use function is_array;
use function is_string;

use const DATE_ATOM;

final class AuthorizationStateData
{
    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    public static function privileges(array $data): array
    {
        $privileges = $data['privileges'] ?? [];
        if (! is_array($privileges)) {
            throw new UnexpectedValueException('The authorization privileges must be an array.');
        }

        try {
            $accountPrivileges = new AccountPrivileges($privileges);
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedValueException('The authorization privileges are invalid.', previous: $exception);
        }

        return $accountPrivileges->slugs();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function formatDates(array $data): array
    {
        foreach ($data as $key => $value) {
            if (! ($value instanceof DateTimeImmutable)) {
                continue;
            }

            $data[$key] = $value->format(DATE_ATOM);
        }

        return $data;
    }

    /** @return list<non-empty-string> */
    public static function tokenIdentifiers(mixed $userData): array
    {
        if (! is_array($userData) || ! is_array($userData['tokens'] ?? null)) {
            return [];
        }

        $identifiers = [];
        foreach ($userData['tokens'] as $token) {
            if (! is_array($token)) {
                continue;
            }

            $identifier = $token['jti'] ?? null;
            if (! is_string($identifier) || $identifier === '') {
                continue;
            }

            $identifiers[] = $identifier;
        }

        return $identifiers;
    }
}
