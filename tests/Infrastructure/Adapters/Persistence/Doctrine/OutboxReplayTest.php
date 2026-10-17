<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Infrastructure\Adapters\Console\Queue\ReplayOutboxMessage;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Outbox\DoctrineOutboxReplay;
use Backendbase\Infrastructure\Adapters\Queue\InMemoryExternalIntegrationEventRegistry;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\Persistence\MessageRecoveryFixture;
use Throwable;
use UnexpectedValueException;

final class OutboxReplayTest extends TestCase
{
    public function testPreviewAndApplyPreserveOriginalIdentityPayloadAndConsumer(): void
    {
        $fixture = MessageRecoveryFixture::create();
        $fixture->outbox();
        $connection = $fixture->connection();
        $before     = $connection->fetchAssociative('SELECT * FROM integration_event_outbox');
        $tester     = new CommandTester(new ReplayOutboxMessage(self::replay($fixture)));
        $options    = ['--message-id' => 'original-message', '--operator' => 'operator-one', '--evidence' => 'incident:123'];
        self::assertSame(0, $tester->execute($options));
        self::assertSame($before, $connection->fetchAssociative('SELECT * FROM integration_event_outbox'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM integration_event_replay'));
        self::assertSame(0, $tester->execute([...$options, '--apply' => true]));
        $after = $connection->fetchAssociative('SELECT * FROM integration_event_outbox');
        self::assertIsArray($before);
        self::assertIsArray($after);
        self::assertSame([...$before, 'published_at' => null, 'available_at' => '2026-10-14 10:00:00.000000'], $after);
        self::assertSame(['consumer_name' => 'events', 'operator_name' => 'operator-one', 'evidence_reference' => 'incident:123'], $connection->fetchAssociative('SELECT consumer_name, operator_name, evidence_reference FROM integration_event_replay'));
    }

    /**
     * @param array<string, mixed>    $changes
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('ineligibleRecords')]
    public function testReplayRejectsUnsafeRecordsWithoutMutation(array $changes, string $exception): void
    {
        $fixture = MessageRecoveryFixture::create();
        $fixture->outbox($changes);
        $this->expectException($exception);
        self::replay($fixture)->schedule('original-message', new RecoveryEvidence('operator', 'incident:123'), true);
    }

    /** @return iterable<string, array{array<string, mixed>, class-string<Throwable>}> */
    public static function ineligibleRecords(): iterable
    {
        yield 'unpublished' => [['published_at' => null], RuntimeException::class];
        yield 'missing destination' => [['destination' => null], RuntimeException::class];
        yield 'active claim' => [['claim_expires_at' => '2026-10-14 10:00:00.000000'], RuntimeException::class];
        yield 'expired replay age' => [['occurred_at' => '2026-10-07 09:59:59.999999'], UnexpectedValueException::class];
        yield 'unsupported event version' => [['event_version' => '2.0'], UnexpectedValueException::class];
    }

    public function testUnknownExternalOutcomeBlocksReplay(): void
    {
        $fixture = MessageRecoveryFixture::create();
        $fixture->outbox();
        $fixture->inbox();
        $this->expectException(RuntimeException::class);
        self::replay($fixture)->schedule('original-message', new RecoveryEvidence('operator', 'incident:123'), true);
    }

    public function testMissingMessageAndMissingCliEvidenceAreRejected(): void
    {
        $fixture = MessageRecoveryFixture::create();
        $replay  = self::replay($fixture);
        $tester  = new CommandTester(new ReplayOutboxMessage($replay));
        self::assertSame(2, $tester->execute([]));
        $this->expectException(RuntimeException::class);
        $replay->schedule('missing', new RecoveryEvidence('operator', 'incident:123'));
    }

    private static function replay(MessageRecoveryFixture $fixture): DoctrineOutboxReplay
    {
        $registry = new InMemoryExternalIntegrationEventRegistry([['eventName' => 'Example_NewExampleAdded_Event', 'eventVersion' => '1.0', 'messageFQCN' => EntryAddedMessage::class]]);

        return new DoctrineOutboxReplay($fixture->connection(), $fixture->clock(), $registry);
    }
}
