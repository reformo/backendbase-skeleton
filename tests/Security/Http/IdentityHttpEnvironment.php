<?php

declare(strict_types=1);

namespace Tests\Security\Http;

use Backendbase\Domain\IdentityAndAccess\Contracts\CredentialStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use DateTimeImmutable;
use DI\ContainerBuilder;
use Doctrine\DBAL\DriverManager;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use RuntimeException;
use Tests\Support\Services\IsolatedMySql;

use function getenv;
use function password_hash;
use function preg_match;

use const PASSWORD_ARGON2ID;

final class IdentityHttpEnvironment
{
    public static function create(): string
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            throw new RuntimeException('Prepared service targets are required.');
        }

        $fixture    = IsolatedMySql::create(false);
        $connection = $fixture->connection();
        $database   = $connection->getDatabase();
        $connection->close();

        return $database ?? throw new RuntimeException('The fixture database is unavailable.');
    }

    public static function destroy(string $database): void
    {
        if (preg_match('/\Abackendbase_adr_[0-9a-f]{16}\z/D', $database) !== 1) {
            throw new RuntimeException('Only a fixture database can be removed.');
        }

        $password = getenv('BACKENDBASE_TEST_MYSQL_PASSWORD');
        if ($password === false || $password === '') {
            throw new RuntimeException('The isolated MySQL test password is required.');
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => '127.0.0.1',
            'port' => (int) getenv('BACKENDBASE_TEST_MYSQL_PORT'),
            'user' => 'root',
            'password' => $password,
        ]);
        $connection->executeStatement('DROP DATABASE ' . $database);
        $connection->close();
    }

    /** @return array{accountId: string, actorId: string} */
    public static function seed(): array
    {
        $builder       = new ContainerBuilder();
        $configuration = new ConfigAggregator([new PhpFileProvider('config/autoload/*')]);
        $settings      = require 'config/settings.php';
        $settings($builder, $configuration->getMergedConfig());
        $dependencies = require 'config/dependencies.php';
        $dependencies($builder);
        $container   = $builder->build();
        $store       = $container->get(IdentityStore::class);
        $credentials = $container->get(CredentialStore::class);

        $suffix = getenv('BACKENDBASE_HTTP_FIXTURE_SUFFIX');
        if ($suffix === false || preg_match('/\A[0-9]{9}\z/D', $suffix) !== 1) {
            throw new RuntimeException('A unique fixture suffix is required.');
        }

        return $store->transaction(static function () use ($store, $credentials, $suffix): array {
            $id      = IdentityId::create();
            $now     = new DateTimeImmutable();
            $account = Account::create($id, new AccountEmail('operator-' . $suffix . '@example.test'), new AccountMobile('+901' . $suffix), $now);
            $account->verifyOwnership();
            $admin = SystemAdmin::invite(IdentityId::create(), $id, 'http-fixture:invitation', $now);
            $admin->approve('http-fixture:approval');
            $store->saveAccount($account);
            $store->saveActor(User::create(IdentityId::create(), $id, $now));
            $store->saveActor($admin);
            $accountId = $account->id();
            $credentials->save($accountId, ['passwordHash' => password_hash('http-fixture-password', PASSWORD_ARGON2ID)]);

            return ['accountId' => $accountId, 'actorId' => $admin->id()];
        });
    }
}
