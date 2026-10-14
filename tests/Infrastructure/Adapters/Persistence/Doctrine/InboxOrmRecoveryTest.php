<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\Entity\EntryRecord;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Doctrine\DoctrineEntryRepositoryTestCase;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineInboxMessageTransaction;
use Backendbase\Shared\Integrations\Operation\ConsumerRestartRequired;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Ramsey\Uuid\Uuid;
use Tests\Support\Time\FrozenClock;

final class InboxOrmRecoveryTest extends DoctrineEntryRepositoryTestCase
{
    public function testFailedFlushStopsTheConsumerAndAFreshUnitProcessesTheNextMessage(): void
    {
        $connection = $this->connection;
        $connection->executeStatement('CREATE TABLE integration_event_inbox (consumer_name TEXT, message_id TEXT, event_name TEXT, received_at TEXT, processed_at TEXT, PRIMARY KEY (consumer_name, message_id))');
        $clock       = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $manager     = $this->manager();
        $transaction = new DoctrineInboxMessageTransaction($manager, $clock);
        try {
            $transaction->processOnce('events', 'failed-message', 'Example_Added_Event', static function () use ($manager): void {
                $manager->persist(self::entry('duplicate-key'));
                $manager->persist(self::entry('duplicate-key'));
            });
            self::fail('An ORM execution failure must stop the consumer.');
        } catch (ConsumerRestartRequired $exception) {
            self::assertInstanceOf(UniqueConstraintViolationException::class, $exception->getPrevious());
            self::assertSame(QueueMessageHandlingOutcome::RETRY, $exception->outcome());
        }

        self::assertFalse($manager->isOpen());
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM example_table'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM integration_event_inbox'));
        $freshManager     = $this->manager();
        $freshTransaction = new DoctrineInboxMessageTransaction($freshManager, $clock);
        $freshTransaction->processOnce('events', 'next-message', 'Example_Added_Event', static function () use ($freshManager): void {
            $freshManager->persist(self::entry('next-key'));
        });
        self::assertTrue($freshManager->isOpen());
        self::assertSame('next-key', $connection->fetchOne('SELECT lookup_key FROM example_table'));
        self::assertSame('2026-10-14 10:00:00.000000', $connection->fetchOne('SELECT processed_at FROM integration_event_inbox'));
    }

    private function manager(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Doctrine/Entity'], true);
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($this->connection, $configuration);
    }

    private static function entry(string $key): EntryRecord
    {
        $id    = Uuid::uuid7();
        $entry = Entry::create($id->toString(), EntryType::SYSTEM, null, 'settings', true, $key, 'value', []);

        return EntryRecord::fromDomain($entry);
    }
}
