<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Contacts;

use Backendbase\Shared\Exception\InvalidUserInput;

use function base64_decode;
use function base64_encode;
use function hash;
use function hash_equals;
use function preg_match;
use function rtrim;
use function strtr;

final readonly class VerificationContextToken
{
    private string $digest;

    public function __construct(string $value)
    {
        $bytes = base64_decode(strtr($value, '-_', '+/'), true);
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/D', $value) !== 1 || $bytes === false || rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') !== $value) {
            throw InvalidUserInput::create('Supply a canonical 32-byte verification context token.');
        }

        $this->digest = hash('sha256', $value);
    }

    public function digest(): string
    {
        return $this->digest;
    }

    public function matches(string $digest): bool
    {
        return hash_equals($this->digest, $digest);
    }
}
