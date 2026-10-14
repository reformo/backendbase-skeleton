<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Health\ReadinessChecks;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\ORMSetup;
use Pdo\Sqlite;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Infrastructure\Composition\ProductionContainerFixture;
use Tests\Support\Time\FrozenClock;

use function array_replace_recursive;
use function base64_encode;
use function str_repeat;

final readonly class IdentityFixture
{
    public function __construct(private Connection $connection, private ContainerInterface $container)
    {
    }

    public static function create(): self
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $native     = $connection->getNativeConnection();
        if (! $native instanceof Sqlite) {
            throw new RuntimeException('The fixture requires native SQLite.');
        }

        $native->createFunction('BIN_TO_UUID', static function (string $bytes, int $swap): string {
            $uuid = Uuid::fromBytes($bytes);

            return $uuid->toString();
        }, 2, Sqlite::DETERMINISTIC);
        self::schema($connection);
        $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/IdentityAndAccess/Adapters/Persistence/Doctrine/Entity'], true);
        $configuration->enableNativeLazyObjects(true);
        $clock     = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $container = ProductionContainerFixture::build([
            LoggerInterface::class => new NullLogger(),
            ReadinessChecks::class => new ReadinessChecks([]),
            Connection::class => $connection,
            Configuration::class => $configuration,
            Clock::class => $clock,
            IdentitySettings::class => self::settings(),
            OwnershipNotifier::class => new CapturedOwnershipNotifier(),
            TokenRegistry::class => new MemoryTokenRegistry($clock),
        ]);

        return new self($connection, $container);
    }

    /** @param array<string, mixed> $overrides */
    public static function settings(array $overrides = []): IdentitySettings
    {
        return new IdentitySettings(new Settings([
            'identity' => array_replace_recursive([
                'tokenLifetimeSeconds' => 900,
                'encryptionKeyId' => 'factor-key',
                'encryptionKey' => base64_encode(str_repeat('e', 32)),
                'ownershipSender' => 'identity@example.com',
                'USER' => ['issuer' => 'https://user.example.com', 'audience' => 'user-api', 'keyId' => 'user-key', 'key' => base64_encode(str_repeat('u', 32))],
                'ADMIN' => ['issuer' => 'https://admin.example.com', 'audience' => 'system-admin-api', 'keyId' => 'admin-key', 'key' => base64_encode(str_repeat('a', 32))],
            ], $overrides),
        ]));
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    private static function schema(Connection $connection): void
    {
        $base = 'id BLOB NOT NULL PRIMARY KEY, uuid TEXT GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, version INTEGER NOT NULL DEFAULT 1, ';
        $connection->executeStatement('CREATE TABLE identity_account (' . $base . 'normalized_email TEXT NOT NULL, created_at TEXT NOT NULL, deleted_at TEXT NULL, active_email TEXT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_email ELSE NULL END) VIRTUAL UNIQUE, state TEXT NOT NULL, credentials TEXT NOT NULL)');
        foreach (['identity_user', 'identity_admin'] as $table) {
            $connection->executeStatement('CREATE TABLE ' . $table . ' (' . $base . 'account_id BLOB NOT NULL UNIQUE, normalized_login_name TEXT NOT NULL, created_at TEXT NOT NULL, deleted_at TEXT NULL, active_login_name TEXT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_login_name ELSE NULL END) VIRTUAL UNIQUE, state TEXT NOT NULL)');
        }

        $connection->executeStatement('CREATE TABLE identity_challenge (' . $base . 'account_id BLOB NOT NULL, actor_id BLOB NOT NULL, realm TEXT NOT NULL, purpose TEXT NOT NULL, expires_at TEXT NOT NULL, state TEXT NOT NULL)');
        $connection->executeStatement('CREATE TABLE identity_operator_action (id BLOB PRIMARY KEY, uuid TEXT GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL, target_id BLOB NOT NULL, action_name TEXT NOT NULL, bootstrap_key TEXT UNIQUE, operator_name TEXT NOT NULL, invitation_evidence TEXT, approval_evidence TEXT, recorded_at TEXT NOT NULL)');
    }
}
