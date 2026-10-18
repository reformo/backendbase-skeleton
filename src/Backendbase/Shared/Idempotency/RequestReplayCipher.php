<?php

declare(strict_types=1);

namespace Backendbase\Shared\Idempotency;

interface RequestReplayCipher
{
    public function fingerprint(string $content): string;

    public function encrypt(RequestIdentity $identity, string $response): string;

    public function decrypt(RequestIdentity $identity, string $response): string;
}
