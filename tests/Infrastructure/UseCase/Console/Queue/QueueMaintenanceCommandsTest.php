<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\Console\Queue;

use Backendbase\Infrastructure\UseCase\Console\Queue\CleanupIntegrationMessages;
use Backendbase\Infrastructure\UseCase\Console\Queue\RelayOutboxMessages;
use Backendbase\Shared\Integrations\IntegrationMessageLogCleaner;
use Backendbase\Shared\Integrations\Operation\IntegrationMessageLogCleanupResult;
use Backendbase\Shared\Integrations\OutboxRelay;
use Backendbase\Shared\Integrations\Operation\OutboxRelayResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class QueueMaintenanceCommandsTest extends TestCase
{
    #[Test]
    public function itCleansIntegrationMessageLogs(): void
    {
        $cleaner = $this->createMock(IntegrationMessageLogCleaner::class);
        $cleaner->expects(self::once())
            ->method('clean')
            ->with(45, 250)
            ->willReturn(new IntegrationMessageLogCleanupResult(3, 4, 5));
        $tester = new CommandTester(new CleanupIntegrationMessages($cleaner));

        $exitCode = $tester->execute(['--retention-days' => '45', '--limit' => '250']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Deleted outbox: 3; inbox: 4; delivery failures: 5.', $tester->getDisplay());
    }

    #[Test]
    public function itRejectsInvalidCleanupOptions(): void
    {
        $cleaner = $this->createMock(IntegrationMessageLogCleaner::class);
        $cleaner->expects(self::never())->method('clean');
        $tester = new CommandTester(new CleanupIntegrationMessages($cleaner));

        self::assertSame(Command::INVALID, $tester->execute(['--retention-days' => '29']));
        self::assertSame(
            Command::INVALID,
            $tester->execute(['--retention-days' => '30', '--limit' => '10001']),
        );
    }

    #[Test]
    public function itRelaysOutboxMessagesAndReportsFailures(): void
    {
        $relay = $this->createMock(OutboxRelay::class);
        $relay->expects(self::exactly(2))
            ->method('relay')
            ->willReturnOnConsecutiveCalls(
                new OutboxRelayResult(2, 0),
                new OutboxRelayResult(1, 1),
            );
        $tester = new CommandTester(new RelayOutboxMessages($relay));

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '10']));
        self::assertStringContainsString('Published: 2; failed: 0.', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '10']));
    }

    #[Test]
    public function itRejectsAnInvalidRelayLimit(): void
    {
        $relay = $this->createMock(OutboxRelay::class);
        $relay->expects(self::never())->method('relay');
        $tester = new CommandTester(new RelayOutboxMessages($relay));

        self::assertSame(Command::INVALID, $tester->execute(['--limit' => '0']));
    }
}
