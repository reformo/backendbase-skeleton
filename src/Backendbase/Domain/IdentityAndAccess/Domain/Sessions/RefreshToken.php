<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Shared\Exception\InvalidUserInput;

use function base64_decode;
use function base64_encode;
use function count;
use function explode;
use function hash;
use function random_bytes;
use function rtrim;
use function strlen;
use function strtr;

final readonly class RefreshToken
{
    /** @var array{string, string, string} */
    private array $parts;

    public function __construct(private string $value)
    {
        $parts = explode('.', $value);
        if (strlen($value) !== 117 || count($parts) !== 3) {
            throw InvalidUserInput::create('The refresh token format is invalid.');
        }

        [$accountId, $sessionId, $secret] = $parts;
        $account                          = IdentityId::fromString($accountId);
        $session                          = IdentityId::fromString($sessionId);
        $canonicalAccountId               = $account->toString();
        $canonicalSessionId               = $session->toString();
        if ($canonicalAccountId !== $accountId || $canonicalSessionId !== $sessionId) {
            throw InvalidUserInput::create('The refresh token identifiers are invalid.');
        }

        $bytes = base64_decode(strtr($secret, '-_', '+/'), true);
        if ($bytes === false || strlen($bytes) !== 32 || rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') !== $secret) {
            throw InvalidUserInput::create('The refresh token secret is invalid.');
        }

        $this->parts = [$accountId, $sessionId, $secret];
    }

    public static function create(Principal $principal): self
    {
        $identity = $principal->identity();
        $secret   = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return new self($identity['accountId'] . '.' . $identity['sid'] . '.' . $secret);
    }

    public function accountId(): string
    {
        return $this->parts[0];
    }

    public function sessionId(): string
    {
        return $this->parts[1];
    }

    public function digest(): string
    {
        return hash('sha256', $this->value);
    }

    public function expose(): string
    {
        return $this->value;
    }
}
