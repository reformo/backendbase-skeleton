<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Shared\Settings;

use function array_intersect;
use function base64_decode;
use function explode;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function strlen;
use function strtolower;

final readonly class IdentitySettings
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(Settings $settings)
    {
        $root     = $settings->get();
        $identity = is_array($root) ? ($root['identity'] ?? []) : null;
        if (! is_array($identity)) {
            throw AuthenticationUnavailable::create('The authentication configuration is unavailable.');
        }

        $this->values = $identity;
    }

    public function tokenLifetimeSeconds(): int
    {
        $value = $this->value('token-lifetime-seconds') ?? 900;
        if (! is_int($value) || $value < 60 || $value > 3600) {
            throw AuthenticationUnavailable::create('The authentication configuration is unavailable.');
        }

        return $value;
    }

    /** @return array{issuer: non-empty-string, audience: non-empty-string, keyId: non-empty-string, keys: array<non-empty-string, non-empty-string>} */
    public function realm(Realm $realm): array
    {
        $policies = [];
        foreach ([Realm::USER, Realm::ADMIN] as $candidate) {
            $prefix                      = strtolower($candidate->value) . '.';
            $policies[$candidate->value] = [
                'issuer' => $this->string($prefix . 'issuer'),
                'audience' => $this->string($prefix . 'audience'),
                'keyId' => $this->keyId($prefix . 'key-id'),
                'keys' => $this->keys($prefix . 'key-id', $prefix . 'key', $prefix . 'previous-key-id', $prefix . 'previous-key'),
            ];
        }

        $user  = $policies['USER'];
        $admin = $policies['ADMIN'];
        if ($user['issuer'] === $admin['issuer'] || $user['audience'] === $admin['audience'] || array_intersect($user['keys'], $admin['keys']) !== []) {
            throw AuthenticationUnavailable::create('The authentication realms must have separate trust policies.');
        }

        return $realm === Realm::USER ? $user : $admin;
    }

    /** @return array<non-empty-string, non-empty-string> */
    public function contactProofKeys(): array
    {
        return $this->keys('contact-proof-key-id', 'contact-proof-key', 'previous-contact-proof-key-id', 'previous-contact-proof-key');
    }

    public function contactProofKeyId(): string
    {
        return $this->keyId('contact-proof-key-id');
    }

    public function contactEmailSender(): string
    {
        return $this->string('contact-email-sender');
    }

    /** @return array<non-empty-string, non-empty-string> */
    private function keys(string $idName, string $keyName, string $previousIdName, string $previousKeyName): array
    {
        $id          = $this->keyId($idName);
        $keys        = [$id => $this->key($keyName)];
        $previousId  = $this->value($previousIdName);
        $previousKey = $this->value($previousKeyName);
        if (($previousId === '' || $previousId === null) && ($previousKey === '' || $previousKey === null)) {
            return $keys;
        }

        $previousId = $this->keyId($previousIdName);
        if ($previousId === $id) {
            throw AuthenticationUnavailable::create('Active and previous key identifiers must differ.');
        }

        $keys[$previousId] = $this->key($previousKeyName);

        return $keys;
    }

    /** @return non-empty-string */
    private function keyId(string $name): string
    {
        $value = $this->string($name);
        if (preg_match('/\A[A-Za-z0-9._-]{1,64}\z/D', $value) !== 1) {
            throw AuthenticationUnavailable::create('The authentication key identifier is invalid.');
        }

        return $value;
    }

    /** @return non-empty-string */
    private function key(string $name): string
    {
        $decoded = base64_decode($this->string($name), true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw AuthenticationUnavailable::create('Authentication keys must be base64-encoded 32-byte values.');
        }

        return $decoded;
    }

    /** @return non-empty-string */
    private function string(string $name): string
    {
        $value = $this->value($name);
        if (! is_string($value) || $value === '' || strlen($value) > 255 || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            throw AuthenticationUnavailable::create('The authentication configuration is unavailable.');
        }

        return $value;
    }

    private function value(string $path): mixed
    {
        $value = $this->values;
        foreach (explode('.', $path) as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }

        return $value;
    }
}
