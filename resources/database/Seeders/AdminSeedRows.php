<?php

declare(strict_types=1);

namespace Backendbase\Seeders;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Operator\DoctrineOperatorStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Ramsey\Uuid\Uuid;

use function json_encode;
use function password_hash;

use const JSON_THROW_ON_ERROR;
use const PASSWORD_ARGON2ID;

final readonly class AdminSeedRows
{
    public function __construct(private Connection $connection)
    {
    }

    public function insert(Account $account, SystemAdmin $admin, Password $password, DateTimeImmutable $now): void
    {
        $connection = $this->connection;
        $state      = $account->snapshot();
        $accountId  = $account->id();
        $identifier = Uuid::fromString($accountId);
        $connection->insert('identity_account', [
            'id' => $identifier->getBytes(),
            'version' => 1,
            'normalized_email' => $state['email'],
            'normalized_mobile' => $state['mobile'],
            'first_name' => $state['firstName'] ?? null,
            'last_name' => $state['lastName'] ?? null,
            'created_at' => $state['createdAt'],
            'deleted_at' => null,
            'state' => json_encode(['owned' => true, 'enabled' => true, 'authVersion' => 1], JSON_THROW_ON_ERROR),
            'credentials' => json_encode(['passwordHash' => password_hash($password->forHashing(), PASSWORD_ARGON2ID)], JSON_THROW_ON_ERROR),
        ], ['id' => ParameterType::BINARY]);
        $actorId = $admin->id();
        $actor   = Uuid::fromString($actorId);
        $state   = $admin->snapshot();
        $connection->insert('identity_admin', [
            'id' => $actor->getBytes(),
            'version' => 1,
            'account_id' => $identifier->getBytes(),
            'created_at' => $state['createdAt'],
            'deleted_at' => null,
            'state' => json_encode(['approved' => $state['approved'], 'invitation' => $state['invitation'], 'approval' => $state['approval']], JSON_THROW_ON_ERROR),
        ], ['id' => ParameterType::BINARY, 'account_id' => ParameterType::BINARY]);
        $evidence = new DoctrineOperatorStore($connection);
        $evidence->record(new OperatorInput('invite-first-admin', $accountId, 'admin-seeder', AdminSeeder::INVITATION, ''), $actorId, $now);
        $evidence->record(new OperatorInput('approve-first-admin', $actorId, 'admin-seeder', '', AdminSeeder::APPROVAL), $actorId, $now);
    }
}
