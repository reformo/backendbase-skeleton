<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Application\Workflows;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\ActorChangeInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AdminApprovalInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AdminInvitationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use DateTimeImmutable;

final readonly class AdministrationWorkflow
{
    public function __construct(private IdentityStore $store, private AuthenticationGate $gate)
    {
    }

    /** @return array{actorId: string, approved: bool} */
    public function invite(Principal $principal, AdminInvitationInput $input, DateTimeImmutable $now): array
    {
        $currentStore = $this->store;

        $id = IdentityId::create();
        $currentStore->transaction(function () use ($principal, $input, $id, $now): void {
            $currentGate  = $this->gate;
            $currentStore = $this->store;

            $principal->assertAdmin();
            $currentGate->assertCurrent($principal);
            $account = $currentStore->account($input->accountId());
            $state   = $account->snapshot();
            $account->assertSessionAllowed($state['authVersion']);
            $identity = $principal->identity();
            $actor    = SystemAdmin::invite($id, IdentityId::fromString($account->id()), 'invite:' . $identity['actorId'] . ':' . $input->evidence(), $now);
            $currentStore->saveActor($actor);
        });

        return ['actorId' => $id->toString(), 'approved' => false];
    }

    public function approve(Principal $principal, AdminApprovalInput $input): void
    {
        $currentStore = $this->store;

        $currentStore->transaction(function () use ($principal, $input): void {
            $currentStore = $this->store;
            $currentGate  = $this->gate;

            $principal->assertAdmin();
            $identity = $principal->identity();
            if ($input->actorId() === $identity['actorId']) {
                throw ResourceAccessForbidden::create('An Admin cannot approve its own enrollment.');
            }

            $reference      = $currentStore->actorReference(Realm::ADMIN, $input->actorId());
            $referenceState = $reference->snapshot();
            $currentStore->protectActorMutation($identity['accountId'], $referenceState['accountId']);
            $currentGate->assertCurrent($principal);
            $actor = $currentStore->actorForUpdate(Realm::ADMIN, $input->actorId());
            if (! $actor instanceof SystemAdmin) {
                throw ResourceAccessForbidden::create('The target actor must be a SystemAdmin.');
            }

            $state        = $actor->snapshot();
            $account      = $currentStore->account($state['accountId']);
            $accountState = $account->snapshot();
            $account->assertSessionAllowed($accountState['authVersion']);
            $actor->approve('approve:' . $identity['actorId'] . ':' . $input->evidence());
            $currentStore->saveActor($actor);
        });
    }

    public function changeActor(Principal $principal, ActorChangeInput $input, DateTimeImmutable $now): void
    {
        $currentStore = $this->store;

        $currentStore->transaction(function () use ($principal, $input, $now): void {
            $currentStore = $this->store;
            $currentGate  = $this->gate;

            $principal->assertAdmin();
            $identity       = $principal->identity();
            $reference      = $currentStore->actorReference($input->realm(), $input->actorId());
            $referenceState = $reference->snapshot();
            $currentStore->protectActorMutation($identity['accountId'], $referenceState['accountId']);
            $currentGate->assertCurrent($principal);
            $actor = $currentStore->actorForUpdate($input->realm(), $input->actorId());
            if ($input->action() === 'restore') {
                $actor->restore();
                $currentStore->saveActor($actor);

                return;
            }

            $actor->remove($now);
            $currentStore->saveActor($actor);
        });
    }
}
