<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Migrations;

use Backendbase\Migrations\Version20261014020000;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\StringType;
use Doctrine\Migrations\Query\Query;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function array_map;
use function str_replace;

final class IdentityContactSchemaTest extends TestCase
{
    public function testInstallationMatchesProductionContactMappingsAndIndexes(): void
    {
        require 'config/doctrine-types.php';
        $connection    = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/IdentityAndAccess/Adapters/Persistence/Doctrine/Entity'], true);
        $configuration->enableNativeLazyObjects(true);
        $manager      = new EntityManager($connection, $configuration);
        $factory      = $manager->getMetadataFactory();
        $metadata     = $factory->getAllMetadata();
        $schema       = new SchemaTool($manager)->getSchemaFromMetadata($metadata);
        $account      = $schema->getTable('identity_account');
        $passwordHash = $account->getColumn('password_hash');
        self::assertInstanceOf(StringType::class, $passwordHash->getType());
        self::assertTrue($passwordHash->getNotnull());
        self::assertSame(255, $passwordHash->getLength());
        self::assertFalse($account->hasColumn('credentials'));
        $mobile = $account->getColumn('normalized_mobile');
        self::assertTrue($mobile->getNotnull());
        self::assertFalse($account->getColumn('first_name')->getNotnull());
        self::assertFalse($account->getColumn('last_name')->getNotnull());
        self::assertSame(160, $account->getColumn('first_name')->getLength());
        self::assertSame(160, $account->getColumn('last_name')->getLength());
        self::assertSame(16, $mobile->getLength());
        $activeMobileIndex = $account->getIndex('identity_account_active_mobile_unq');
        self::assertTrue($activeMobileIndex->isUnique());
        self::assertSame(['active_mobile'], $activeMobileIndex->getColumns());
        $contact = $schema->getTable('contact_info');
        self::assertSame(64, $contact->getColumn('context_hash')->getLength());
        self::assertSame(254, $contact->getColumn('normalized_value')->getLength());
        self::assertFalse($contact->getColumn('account_id')->getNotnull());
        self::assertFalse($contact->getColumn('actor_id')->getNotnull());
        $contextSlot = $contact->getIndex('contact_info_context_slot_unq');
        self::assertTrue($contextSlot->isUnique());
        self::assertSame(['context_hash', 'realm', 'purpose', 'contact_type'], $contextSlot->getColumns());
        self::assertFalse($schema->hasTable('identity_challenge'));
        $migration = new Version20261014020000($connection, new NullLogger());
        $migration->up(new Schema());
        $statements = array_map(static fn (Query $query): string => str_replace('`', '', $query->getStatement()), $migration->getSql());
        foreach (new SchemaTool($manager)->getCreateSchemaSql($metadata) as $statement) {
            self::assertContains(str_replace('`', '', $statement), $statements);
        }

        $user  = $schema->getTable('identity_user');
        $admin = $schema->getTable('identity_admin');
        self::assertFalse($user->hasColumn('normalized_login_name'));
        self::assertFalse($admin->hasColumn('active_login_name'));
        self::assertFalse($connection->isConnected());
    }
}
