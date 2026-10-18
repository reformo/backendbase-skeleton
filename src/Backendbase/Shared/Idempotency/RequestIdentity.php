<?php

declare(strict_types=1);

namespace Backendbase\Shared\Idempotency;

use InvalidArgumentException;

use function preg_match;

final readonly class RequestIdentity
{
    public function __construct(public string $keyHash, public string $fingerprint)
    {
        foreach ([$keyHash, $fingerprint] as $digest) {
            self::validate($digest);
        }
    }

    private static function validate(string $digest): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new InvalidArgumentException('Request identity requires SHA-256 digests.');
        }
    }
}
