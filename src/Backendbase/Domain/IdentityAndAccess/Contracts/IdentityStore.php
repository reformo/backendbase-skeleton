<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginIdentifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;

interface IdentityStore
{
    /**
     * @param callable(): TResult $work
     *
     * @return TResult
     *
     * @template TResult
     */
    public function transaction(callable $work): mixed;

    public function account(string $id): Account;

    public function accountForUpdate(string $id): Account;

    public function protectActorMutation(string $observerAccountId, string $targetAccountId): void;

    public function accountByEmail(AccountEmail $email): Account|null;

    public function accountByMobile(AccountMobile $mobile): Account|null;

    public function actor(Realm $realm, string $id): Actor;

    public function actorReference(Realm $realm, string $id): Actor;

    public function actorForUpdate(Realm $realm, string $id): Actor;

    public function actorByIdentifier(Realm $realm, LoginIdentifier $identifier): Actor|null;

    public function saveAccount(Account $account): void;

    public function saveActor(Actor $actor): void;
}
