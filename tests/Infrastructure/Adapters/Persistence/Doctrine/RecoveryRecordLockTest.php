<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox\DoctrineExternalEffectResolver;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Outbox\DoctrineOutboxReplay;
use Backendbase\Shared\Integrations\ExternalIntegrationEventRegistry;
use Backendbase\Shared\Persistence\ExternalEffectResolution;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Time\FrozenClock;

final class RecoveryRecordLockTest extends TestCase
{
    #[DataProvider('operations')]
    public function testRecoveryLocksTheTargetRecordBeforeValidation(string $operation, string $table, string $message): void
    {
        $result = $this->createMock(Result::class);
        $fetch  = $result->expects(self::once());
        $fetch->method('fetchAssociative');
        $fetch->willReturn(false);
        $connection = $this->createMock(Connection::class);
        $platform   = $connection->method('getDatabasePlatform');
        $platform->willReturn(new MySQLPlatform());
        $transaction = $connection->method('transactional');
        $transaction->willReturnCallback(static fn (callable $work): mixed => $work($connection));
        $queryBuilder = $connection->method('createQueryBuilder');
        $queryBuilder->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($connection));
        $query = $connection->expects(self::once());
        $query->method('executeQuery');
        $query->with(self::logicalAnd(self::stringContains('FROM ' . $table), self::stringContains('FOR UPDATE')), self::callback(static fn (array $parameters): bool => $parameters === ($operation === 'replay' ? ['id' => 'missing-message'] : ['consumer' => 'events', 'message' => 'missing-message'])));
        $query->willReturn($result);
        $insert = $connection->expects(self::never());
        $insert->method('insert');
        $update = $connection->expects(self::never());
        $update->method('update');
        $delete = $connection->expects(self::never());
        $delete->method('delete');
        $clock    = new FrozenClock(new DateTimeImmutable('2026-10-15T10:00:00Z'));
        $evidence = new RecoveryEvidence('operator', 'incident:123');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        if ($operation === 'replay') {
            $registry     = $this->createMock(ExternalIntegrationEventRegistry::class);
            $messageClass = $registry->expects(self::never());
            $messageClass->method('messageClass');
            $replay = new DoctrineOutboxReplay($connection, $clock, $registry);
            $replay->schedule('missing-message', $evidence, true);

            return;
        }

        $resolver = new DoctrineExternalEffectResolver($connection, $clock);
        $resolver->resolve('events', 'missing-message', ExternalEffectResolution::ACCEPTED, $evidence, true);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function operations(): iterable
    {
        yield 'outbox replay' => ['replay', 'integration_event_outbox', 'A published message with recorded consumer identity is required.'];
        yield 'external effect resolution' => ['resolve', 'integration_event_inbox', 'An unresolved external-effect record is required.'];
    }
}
