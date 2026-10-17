<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Console\Queue\ResolveExternalEffect;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Inbox\DoctrineExternalEffectResolver;
use Backendbase\Shared\Persistence\ExternalEffectResolution;
use Backendbase\Shared\Persistence\RecoveryEvidence;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\Persistence\MessageRecoveryFixture;

final class ExternalEffectResolutionTest extends TestCase
{
    #[DataProvider('decisions')]
    public function testProviderEvidenceControlsRetryAndIsPreserved(ExternalEffectResolution $decision): void
    {
        $fixture = MessageRecoveryFixture::create();
        $fixture->inbox();
        $connection = $fixture->connection();
        $before     = $connection->fetchAssociative('SELECT * FROM integration_event_inbox');
        $resolver   = new DoctrineExternalEffectResolver($connection, $fixture->clock());
        $tester     = new CommandTester(new ResolveExternalEffect($resolver));
        $options    = ['--consumer' => 'events', '--message-id' => 'original-message', '--decision' => $decision->value, '--operator' => 'operator', '--evidence' => 'provider-ticket:123'];
        self::assertSame(0, $tester->execute($options));
        self::assertSame($before, $connection->fetchAssociative('SELECT * FROM integration_event_inbox'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM external_effect_resolution'));
        self::assertSame(0, $tester->execute([...$options, '--apply' => true]));
        self::assertSame($decision->value, $connection->fetchOne('SELECT decision FROM external_effect_resolution'));
        self::assertSame('provider-ticket:123', $connection->fetchOne('SELECT evidence_reference FROM external_effect_resolution'));
        if ($decision === ExternalEffectResolution::ACCEPTED) {
            self::assertSame('2026-10-14 10:00:00.000000', $connection->fetchOne('SELECT processed_at FROM integration_event_inbox'));

            return;
        }

        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM integration_event_inbox'));
    }

    /** @return iterable<string, array{ExternalEffectResolution}> */
    public static function decisions(): iterable
    {
        yield 'accepted' => [ExternalEffectResolution::ACCEPTED];
        yield 'confirmed nonacceptance' => [ExternalEffectResolution::NOT_ACCEPTED];
    }

    /** @param array<string, mixed>|null $row */
    #[DataProvider('protectedRecords')]
    public function testActiveCompletedAndMissingClaimsCannotBeResolved(array|null $row): void
    {
        $fixture = MessageRecoveryFixture::create();
        if ($row !== null) {
            $fixture->inbox($row);
        }

        $resolver = new DoctrineExternalEffectResolver($fixture->connection(), $fixture->clock());
        $this->expectException(RuntimeException::class);
        $resolver->resolve('events', 'original-message', ExternalEffectResolution::NOT_ACCEPTED, new RecoveryEvidence('operator', 'ticket:123'), true);
    }

    /** @return iterable<string, array{array<string, mixed>|null}> */
    public static function protectedRecords(): iterable
    {
        yield 'active' => [['claim_expires_at' => '2026-10-14 10:00:00.000000']];
        yield 'completed' => [['processed_at' => '2026-10-13 10:00:00.000000']];
        yield 'missing' => [null];
    }

    public function testCliRejectsUnknownDecisionsAndMissingEvidence(): void
    {
        $fixture = MessageRecoveryFixture::create();
        $tester  = new CommandTester(new ResolveExternalEffect(new DoctrineExternalEffectResolver($fixture->connection(), $fixture->clock())));
        self::assertSame(2, $tester->execute([]));
        self::assertSame(2, $tester->execute(['--decision' => 'uncertain']));
        $this->expectException(InvalidArgumentException::class);
        new RecoveryEvidence('operator', "ticket\n123");
    }
}
