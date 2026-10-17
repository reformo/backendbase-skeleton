<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Primitives;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;

use function array_diff_key;
use function array_fill_keys;
use function array_key_exists;
use function is_bool;
use function is_int;
use function is_string;

final class StateField
{
    /**
     * @param array<array-key, mixed> $state
     * @param list<string>            $names
     */
    public static function assertKnownFields(array $state, array $names): void
    {
        if (array_diff_key($state, array_fill_keys($names, true)) !== []) {
            throw AuthenticationUnavailable::create('The persisted identity state contains unsupported fields.');
        }
    }

    /** @param array<array-key, mixed> $state */
    public static function string(array $state, string $name): string
    {
        $value = $state[$name] ?? null;
        if (! is_string($value)) {
            throw AuthenticationUnavailable::create('The persisted identity state is invalid.');
        }

        return $value;
    }

    /** @param array<array-key, mixed> $state */
    public static function nullableString(array $state, string $name): string|null
    {
        if (! array_key_exists($name, $state)) {
            throw AuthenticationUnavailable::create('The persisted identity state is invalid.');
        }

        return $state[$name] === null ? null : self::string($state, $name);
    }

    /** @param array<array-key, mixed> $state */
    public static function boolean(array $state, string $name): bool
    {
        $value = $state[$name] ?? null;
        if (! is_bool($value)) {
            throw AuthenticationUnavailable::create('The persisted identity state is invalid.');
        }

        return $value;
    }

    /** @param array<array-key, mixed> $state */
    public static function integer(array $state, string $name): int
    {
        $value = $state[$name] ?? null;
        if (! is_int($value)) {
            throw AuthenticationUnavailable::create('The persisted identity state is invalid.');
        }

        return $value;
    }
}
