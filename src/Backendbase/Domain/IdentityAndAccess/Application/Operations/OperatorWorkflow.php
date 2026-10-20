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
        $action     = $input->action;
        $store      = $this->store;
        $operations = $this->operations;

        $work        = function () use ($input, $action, $now, $apply): string {
            return match ($action) {
                'invite-first-admin' => $this->invite($input, $now, $apply),
                default => $this->approve($input, $apply),
            };
        };
        $transaction = static fn (): string => $store->transaction($work);

        return $operations->exclusive($transaction);
    }

    private function invite(OperatorInput $input, DateTimeImmutable $now, bool $apply): string
    {
        $targetId   = $input->targetId();
        $operations = $this->operations;
        $operations->assertFirstAdminAvailable();
        $store   = $this->store;
        $account = $store->accountForUpdate($targetId);
        $account->assertEligible(Realm::ADMIN);
        $invitation = $input->invitation;
        $id         = IdentityId::create();
        $actor      = SystemAdmin::invite($id, IdentityId::fromString($account->id()), $invitation, $now);
        if ($apply) {
            $store->saveActor($actor);
        }

        return $actor->id();
    }

    private function approve(OperatorInput $input, bool $apply): string
    {
        $targetId   = $input->targetId();
        $operations = $this->operations;
        $operations->assertBootstrapActor($targetId);
        $store            = $this->store;
        $reference        = $store->actorReference(Realm::ADMIN, $targetId);
        $accountReference = $reference->accountReference();
        $accountId        = $accountReference->toString();
        $account          = $store->accountForUpdate($accountId);
        $account->assertEligible(Realm::ADMIN);
        $actor = $store->actorForUpdate(Realm::ADMIN, $targetId);
        if (! $actor instanceof SystemAdmin) {
            throw IdentityConflict::create('A pending first-Admin invitation is required.');
        }

        $actor->assertPendingApproval();
        $approval = $input->approval;
        $actor->approve($approval);
        if ($apply) {
            $store->saveActor($actor);
        }

        return $actor->id();
    }
}
