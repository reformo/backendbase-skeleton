<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Operations;

use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OperatorStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use DateTimeImmutable;

final readonly class OperatorWorkflow
{
    public function __construct(private IdentityStore $store, private OperatorStore $operations)
    {
    }

    public function run(OperatorInput $input, AuthenticationSecrets $secrets, DateTimeImmutable $now, bool $apply): string
    {
        $values = $input->values();
        $store  = $this->store;

        return $store->transaction(function () use ($input, $values, $secrets, $now, $apply): string {
            $target = match ($values['action']) {
                'invite-first-admin' => $this->invite($input, $now, $apply),
                'approve-first-admin' => $this->approve($input, $apply),
                'recover-totp' => $this->recover($values['targetId'], $apply),
                default => $this->rewrap($values['targetId'], $secrets, $apply),
            };
            if ($apply) {
                $operations = $this->operations;
                $operations->record($input, $target, $now);
            }

            return $target;
        });
    }

    private function invite(OperatorInput $input, DateTimeImmutable $now, bool $apply): string
    {
        $values     = $input->values();
        $operations = $this->operations;
        $operations->assertFirstAdminAvailable();
        $store   = $this->store;
        $account = $store->accountForUpdate($values['targetId']);
        $state   = $account->snapshot();
        $account->assertSessionAllowed($state['authVersion']);
        $id    = IdentityId::create();
        $actor = SystemAdmin::invite($id, IdentityId::fromString($account->id()), new LoginName($values['loginName']), $values['invitation'], $now);
        if ($apply) {
            $store->saveActor($actor);
        }

        return $actor->id();
    }

    private function approve(OperatorInput $input, bool $apply): string
    {
        $values     = $input->values();
        $operations = $this->operations;
        $operations->assertBootstrapActor($values['targetId']);
        $store        = $this->store;
        $reference    = $store->actorReference(Realm::ADMIN, $values['targetId']);
        $state        = $reference->snapshot();
        $account      = $store->accountForUpdate($state['accountId']);
        $accountState = $account->snapshot();
        $account->assertSessionAllowed($accountState['authVersion']);
        $actor = $store->actorForUpdate(Realm::ADMIN, $values['targetId']);
        if (! $actor instanceof SystemAdmin || $state['approved'] || $state['deletedAt'] !== null) {
            throw IdentityConflict::create('A pending first-Admin invitation is required.');
        }

        $actor->approve($values['approval']);
        if ($apply) {
            $store->saveActor($actor);
        }

        return $actor->id();
    }

    private function recover(string $accountId, bool $apply): string
    {
        $store   = $this->store;
        $account = $store->accountForUpdate($accountId);
        $account->requireOwnershipVerification();
        $operations                       = $this->operations;
        $credentials                      = $operations->read($accountId);
        $credentials['totpSecret']        = null;
        $credentials['pendingTotpSecret'] = null;
        $credentials['lastTotpStep']      = -1;
        $credentials['failedMfaAttempts'] = 0;
        $credentials['blockedUntil']      = null;
        if ($apply) {
            $store->saveAccount($account);
            $operations->save($accountId, $credentials);
        }

        return $accountId;
    }

    private function rewrap(string $accountId, AuthenticationSecrets $secrets, bool $apply): string
    {
        $operations  = $this->operations;
        $credentials = $operations->read($accountId);
        foreach (['totpSecret', 'pendingTotpSecret'] as $field) {
            $encrypted           = $credentials[$field];
            $credentials[$field] = $encrypted === null ? null : $secrets->encrypt($secrets->decrypt($encrypted));
        }

        if ($apply) {
            $operations->save($accountId, $credentials);
        }

        return $accountId;
    }
}
