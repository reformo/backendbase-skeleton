<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Operations;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OperatorStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use DateTimeImmutable;

final readonly class OperatorWorkflow
{
    public function __construct(private IdentityStore $store, private OperatorStore $operations)
    {
    }

    public function run(OperatorInput $input, DateTimeImmutable $now, bool $apply): string
    {
        $values     = $input->values();
        $store      = $this->store;
        $operations = $this->operations;

        $work        = function () use ($input, $values, $now, $apply): string {
            return match ($values['action']) {
                'invite-first-admin' => $this->invite($input, $now, $apply),
                default => $this->approve($input, $apply),
            };
        };
        $transaction = static fn (): string => $store->transaction($work);

        return $operations->exclusive($transaction);
    }

    private function invite(OperatorInput $input, DateTimeImmutable $now, bool $apply): string
    {
        $values     = $input->values();
        $operations = $this->operations;
        $operations->assertFirstAdminAvailable();
        $store   = $this->store;
        $account = $store->accountForUpdate($values['targetId']);
        $state   = $account->snapshot();
        $account->assertSessionAllowed($state['authVersion'], Realm::ADMIN);
        $id    = IdentityId::create();
        $actor = SystemAdmin::invite($id, IdentityId::fromString($account->id()), $values['invitation'], $now);
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
        $account->assertSessionAllowed($accountState['authVersion'], Realm::ADMIN);
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
}
