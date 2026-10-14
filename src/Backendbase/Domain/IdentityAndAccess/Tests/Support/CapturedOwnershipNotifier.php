<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;

final class CapturedOwnershipNotifier implements OwnershipNotifier
{
    /** @var array<string, string> */
    private array $codes = [];

    public function send(string $challengeId, AccountEmail $email, string $code): void
    {
        $this->codes[$challengeId] = $code;
    }

    public function code(string $id): string
    {
        return $this->codes[$id];
    }
}
