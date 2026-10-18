<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox\DoctrineExternalEffectResolver;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Outbox\DoctrineOutboxReplay;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Migrations\Version20260823000000;
use Backendbase\Migrations\Version20261014020000;
use Backendbase\Migrations\Version20261017000000;
use Backendbase\Migrations\Version20261017010000;
use Backendbase\Shared\Persistence\ExternalEffectResolution;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Time\FrozenClock;

use function getenv;
use function in_array;

final class MySqlRecoveryTest extends TestCase
{
    public function testMigrationMappingAndLockedManualRecoveryOnMySql84(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        try {
            $connection = $fixture->connection();
            foreach ([Version20260823000000::class, Version20261014020000::class, Version20261017000000::class, Version20261017010000::class] as $class) {
                $migration = new $class($connection, new NullLogger());
                $migration->up(new Schema());
                $queries = $migration->getSql();
                foreach ($queries as $query) {
                    $connection->executeStatement($query->getStatement());
                }
            }

            $configuration = $connection->getConfiguration();
            // Messaging recovery tables are DBAL-owned and have no identity ORM mapping.
            $configuration->setSchemaAssetsFilter(static fn (string $table): bool => in_array($table, ['account', 'user', 'admin', 'contact_verification'], true));
            $manager         = $fixture->manager();
            $metadataFactory = $manager->getMetadataFactory();
            self::assertSame([], new SchemaTool($manager)->getUpdateSchemaSql($metadataFactory->getAllMetadata()));
            $id  = Uuid::uuid7()->toString();
            $now = '2026-10-14 10:00:00.000000';
            $connection->insert('integration_event_outbox', ['id' => $id, 'event_name' => 'Example_NewExampleAdded', 'event_version' => '1.0', 'payload' => '{}', 'occurred_at' => $now, 'created_at' => $now, 'available_at' => $now, 'published_at' => $now, 'destination' => 'events']);
            $connection->insert('integration_event_inbox', ['consumer_name' => 'events', 'message_id' => $id, 'event_name' => 'notification/email', 'received_at' => $now, 'claim_expires_at' => '2026-10-14 09:00:00.000000']);
            $clock    = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
            $evidence = new RecoveryEvidence('operator', 'provider-ticket:123');
            $resolver = new DoctrineExternalEffectResolver($connection, $clock);
            $resolver->resolve('events', $id, ExternalEffectResolution::NOT_ACCEPTED, $evidence, true);
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM integration_event_inbox'));
            $registry = new InMemoryExternalIntegrationEventRegistry([['event-name' => 'Example_NewExampleAdded_Event', 'event-version' => '1.0', 'message-fqcn' => EntryAddedMessage::class]]);
            new DoctrineOutboxReplay($connection, $clock, $registry)->schedule($id, $evidence, true);
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM integration_event_replay'));
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM external_effect_resolution'));
            self::assertSame(null, $connection->fetchOne('SELECT published_at FROM integration_event_outbox'));
            self::assertSame($id, $connection->fetchOne('SELECT message_id FROM integration_event_replay'));
        } finally {
            $fixture->destroy();
        }
    }
}
