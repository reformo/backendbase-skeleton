<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineExternalEffectInbox;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox\DoctrineExternalEffectResolver;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox\ExternalEffectClaims;
use Backendbase\Shared\Persistence\ExternalEffectResolution;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Time\FrozenClock;

final class ExternalEffectResolutionStateTest extends TestCase
{
    #[DataProvider('resolutions')]
    public function testResolutionFencesTheOriginalWorkerAndControlsRedelivery(ExternalEffectResolution $decision, int $expectedCalls): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE integration_event_inbox (consumer_name TEXT, message_id TEXT, event_name TEXT, received_at TEXT, processed_at TEXT, claim_expires_at TEXT, claim_token TEXT, PRIMARY KEY (consumer_name, message_id))');
        $connection->executeStatement('CREATE TABLE external_effect_resolution (id BLOB PRIMARY KEY, consumer_name TEXT, message_id TEXT, decision TEXT, resolved_at TEXT, operator_name TEXT, evidence_reference TEXT)');
        $clock  = new FrozenClock(new DateTimeImmutable('2026-10-14 10:00:00 UTC'));
        $claims = new ExternalEffectClaims($connection);
        $now    = $clock->now();
        $token  = $claims->claim('email', 'message-id', 'Notification_Email', $now);
        self::assertIsString($token);
        $clock->advance(new DateInterval('PT301S'));
        $resolver = new DoctrineExternalEffectResolver($connection, $clock);
        $resolver->resolve('email', 'message-id', $decision, new RecoveryEvidence('operator', 'ticket:123'), true);
        $inbox = new DoctrineExternalEffectInbox($connection, $clock);
        $calls = 0;
        $inbox->processOnce('email', 'message-id', 'Notification_Email', static function () use (&$calls): void {
            $calls++;
        });
        self::assertSame($expectedCalls, $calls);
        $recordedDecision = $connection->fetchOne('SELECT decision FROM external_effect_resolution');
        self::assertSame($decision->value, $recordedDecision);
        $later = $clock->now();
        $this->expectException(RuntimeException::class);
        $claims->complete('email', 'message-id', $token, $later);
    }

    /** @return iterable<string, array{ExternalEffectResolution, int}> */
    public static function resolutions(): iterable
    {
        yield 'accepted' => [ExternalEffectResolution::ACCEPTED, 0];
        yield 'not accepted' => [ExternalEffectResolution::NOT_ACCEPTED, 1];
    }
}
