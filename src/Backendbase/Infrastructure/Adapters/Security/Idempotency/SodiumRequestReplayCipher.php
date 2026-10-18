<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Security\Idempotency;

use Backendbase\Infrastructure\Configuration\Idempotency\ReplaySettings;
use Backendbase\Shared\Idempotency\RequestIdentity;
use Backendbase\Shared\Idempotency\RequestReplayCipher;
use RuntimeException;

use function base64_decode;
use function base64_encode;
use function hash_hmac;
use function random_bytes;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function strlen;
use function substr;

use const SODIUM_CRYPTO_SECRETBOX_MACBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

final readonly class SodiumRequestReplayCipher implements RequestReplayCipher
{
    public function __construct(private ReplaySettings $settings)
    {
    }

    public function fingerprint(string $content): string
    {
        $settings = $this->settings;

        return hash_hmac('sha256', 'fingerprint:' . $content, $settings->key());
    }

    public function encrypt(RequestIdentity $identity, string $response): string
    {
        $nonce     = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($response, $nonce, $this->responseKey($identity));

        return base64_encode($nonce . $encrypted);
    }

    public function decrypt(RequestIdentity $identity, string $response): string
    {
        $decoded = base64_decode($response, true);
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('The stored request response is invalid.');
        }

        $nonce     = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain     = sodium_crypto_secretbox_open($encrypted, $nonce, $this->responseKey($identity));
        if ($plain === false) {
            throw new RuntimeException('The stored request response cannot be authenticated.');
        }

        return $plain;
    }

    private function responseKey(RequestIdentity $identity): string
    {
        $settings    = $this->settings;
        $keyHash     = $identity->keyHash;
        $fingerprint = $identity->fingerprint;

        return hash_hmac('sha256', 'response:' . $keyHash . ':' . $fingerprint, $settings->key(), true);
    }
}
