<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application;

use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\ChallengePurpose;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use DateTimeImmutable;

final readonly class Challenges
{
    public function __construct(private AuthenticationClock $clock, private OwnershipNotifier $notifier)
    {
    }

    public function create(string $id, Account $account, Actor $actor, ChallengePurpose $purpose, string $hash = ''): Challenge
    {
        $currentClock = $this->clock;

        $accountState = $account->snapshot();
        $realm        = $actor->realm();
        $now          = $currentClock->now();
        $expires      = $now->modify('+300 seconds');

        return new Challenge(IdentityId::fromString($id), [
            'accountId' => $account->id(),
            'actorId' => $actor->id(),
            'realm' => $realm->value,
            'purpose' => $purpose->value,
            'proofHash' => $hash,
            'expiresAt' => $expires->format('Y-m-d H:i:s.u'),
            'attempts' => 0,
            'consumed' => false,
            'authVersion' => $accountState['authVersion'],
            'revision' => 1,
        ]);
    }

    public function sendOwnership(string $id, AccountEmail $email, string $code): void
    {
        $currentNotifier = $this->notifier;

        $currentNotifier->send($id, $email, $code);
    }

    public function now(): DateTimeImmutable
    {
        $currentClock = $this->clock;

        return $currentClock->now();
    }

    public function sessionExpiresAt(): DateTimeImmutable
    {
        $currentClock = $this->clock;

        return $currentClock->sessionExpiresAt();
    }
}
