<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

use SensitiveParameterValue;

use function password_hash;
use function password_verify;

use const PASSWORD_ARGON2ID;

class PasswordHash
{
    public const string HASH_ALGO = PASSWORD_ARGON2ID;

    private function __construct(private string $hash)
    {
    }

    public static function create(string $hash): self
    {
        return new self($hash);
    }

    public static function validatePassword(SensitiveParameterValue $password): void
    {
    }

    public function toString(): string
    {
        return $this->hash;
    }

    public function verifyHash(SensitiveParameterValue $password): bool
    {
        return password_verify((string) $password->getValue(), $this->hash);
    }

    public function changeHashWithNewPassword(SensitiveParameterValue $newPassword): self
    {
        $hash      = password_hash((string) $newPassword->getValue(), self::HASH_ALGO);
        $new       = clone $this;
        $new->hash = $hash;

        return $new;
    }
}
