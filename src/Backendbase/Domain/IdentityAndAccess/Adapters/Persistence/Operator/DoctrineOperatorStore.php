<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Operator;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OperatorStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

final readonly class DoctrineOperatorStore implements OperatorStore
{
    public function __construct(private Connection $connection)
    {
    }

    public function assertFirstAdminAvailable(): void
    {
        $connection = $this->connection;
        if ((int) $connection->fetchOne('SELECT COUNT(*) FROM identity_admin') !== 0) {
            throw IdentityConflict::create('First-Admin setup is available only before any Admin record exists.');
        }
    }

    public function assertBootstrapActor(string $actorId): void
    {
        $connection = $this->connection;
        $id         = Uuid::fromString($actorId);
        if ((int) $connection->fetchOne("SELECT COUNT(*) FROM identity_operator_action WHERE bootstrap_key = 'invite-first-admin' AND target_id = ?", [$id->getBytes()]) !== 1) {
            throw IdentityConflict::create('Only the recorded first-Admin invitation can use operator approval.');
        }
    }

    public function record(OperatorInput $input, string $targetId, DateTimeImmutable $now): void
    {
        $values     = $input->values();
        $id         = Uuid::uuid7();
        $target     = Uuid::fromString($targetId);
        $connection = $this->connection;
        $connection->insert('identity_operator_action', [
            'id' => $id->getBytes(),
            'target_id' => $target->getBytes(),
            'action_name' => $values['action'],
            'bootstrap_key' => $values['action'],
            'operator_name' => $values['operator'],
            'invitation_evidence' => $values['invitation'] === '' ? null : $values['invitation'],
            'approval_evidence' => $values['approval'] === '' ? null : $values['approval'],
            'recorded_at' => $now->format('Y-m-d H:i:s.u'),
        ]);
    }
}
