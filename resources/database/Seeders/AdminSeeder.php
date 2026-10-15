<?php

declare(strict_types=1);

namespace Backendbase\Seeders;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\StateField;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Profiles\AccountNames;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Ramsey\Uuid\Uuid;

use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class AdminSeeder
{
    public const string EMAIL      = 'mehmet.korkmaz@reformo.net';
    public const string MOBILE     = '+90555555555';
    public const string INVITATION = 'seed:initial-admin:invitation';
    public const string APPROVAL   = 'seed:initial-admin:approval';

    public function __construct(private Connection $connection, private Clock $clock)
    {
    }

    /** @return array{accountId: string, actorId: string, created: bool} */
    public function seed(Password $password): array
    {
        $connection = $this->connection;

        return $connection->transactional(fn (): array => $this->create($password));
    }

    /** @return array{accountId: string, actorId: string, created: bool} */
    private function create(Password $password): array
    {
        $connection = $this->connection;
        $account    = $connection->fetchAssociative('SELECT uuid, normalized_mobile, first_name, last_name, state FROM identity_account WHERE active_email = :email', ['email' => self::EMAIL]);
        if ($account !== false) {
            return $this->existing($account);
        }

        if ($connection->fetchOne('SELECT id FROM identity_account WHERE active_mobile = :mobile', ['mobile' => self::MOBILE]) !== false || (int) $connection->fetchOne('SELECT COUNT(*) FROM identity_admin') !== 0) {
            throw IdentityConflict::create('Initial Admin seeding requires unused contacts and no existing Admin.');
        }

        $clock     = $this->clock;
        $now       = $clock->now();
        $accountId = IdentityId::create();
        $actorId   = IdentityId::create();
        $account   = Account::create($accountId, new AccountEmail(self::EMAIL), new AccountMobile(self::MOBILE), $now, new AccountNames('Mehmet', 'Korkmaz'));
        $account->verifyOwnership();
        $admin = SystemAdmin::invite($actorId, $accountId, self::INVITATION, $now);
        $admin->approve(self::APPROVAL);
        new AdminSeedRows($connection)->insert($account, $admin, $password, $now);

        return ['accountId' => $accountId->toString(), 'actorId' => $actorId->toString(), 'created' => true];
    }

    /**
     * @param array<string, mixed> $account
     *
     * @return array{accountId: string, actorId: string, created: bool}
     */
    private function existing(array $account): array
    {
        $accountId  = StateField::string($account, 'uuid');
        $identifier = Uuid::fromString($accountId);
        $connection = $this->connection;
        $admin      = $connection->fetchAssociative('SELECT uuid, deleted_at, state FROM identity_admin WHERE account_id = :account', ['account' => $identifier->getBytes()], ['account' => ParameterType::BINARY]);
        if ($admin === false || $account['normalized_mobile'] !== self::MOBILE || $account['first_name'] !== 'Mehmet' || $account['last_name'] !== 'Korkmaz' || $admin['deleted_at'] !== null) {
            throw IdentityConflict::create('The existing Account cannot be replaced or granted Admin access by this seeder.');
        }

        $accountState = self::state($account);
        $adminState   = self::state($admin);
        if (! StateField::boolean($accountState, 'owned') || ! StateField::boolean($accountState, 'enabled') || ! StateField::boolean($adminState, 'approved') || StateField::string($adminState, 'invitation') !== self::INVITATION || StateField::string($adminState, 'approval') !== self::APPROVAL) {
            throw IdentityConflict::create('The seeded Admin state has changed. No existing record was modified.');
        }

        return ['accountId' => $accountId, 'actorId' => StateField::string($admin, 'uuid'), 'created' => false];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function state(array $row): array
    {
        $state = json_decode(StateField::string($row, 'state'), true, 16, JSON_THROW_ON_ERROR);
        if (! is_array($state)) {
            throw IdentityConflict::create('The persisted initial Admin state is invalid.');
        }

        $fields = [];
        foreach ($state as $name => $value) {
            $fields[is_string($name) ? $name : throw IdentityConflict::create('The persisted initial Admin state is invalid.')] = $value;
        }

        return $fields;
    }
}
