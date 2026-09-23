<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\Console\Queue;

use Backendbase\Infrastructure\UseCase\Console\Queue\CleanupIntegrationMessages;
use Backendbase\Infrastructure\UseCase\Console\Queue\RelayOutboxMessages;
use Backendbase\Shared\Integrations\IntegrationMessageLogCleaner;
use Backendbase\Shared\Integrations\Operation\IntegrationMessageLogCleanupResult;
use Backendbase\Shared\Integrations\Operation\OutboxRelayResult;
use Backendbase\Shared\Integrations\OutboxRelay;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function microtime;

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
        self::assertSame(Command::INVALID, $tester->execute(['--limit' => '1001', '--continuous' => true]));
    }

    #[Test]
    public function itContinuouslyRelaysFullBatchesAndPollsAfterPartialOrEmptyBatches(): void
    {
        $calls = 0;
        $relay = $this->createMock(OutboxRelay::class);
        $relay->expects(self::exactly(4))
            ->method('relay')
            ->with(2)
            ->willReturnCallback(static function () use (&$calls): OutboxRelayResult {
                ++$calls;

                return match ($calls) {
                    1 => new OutboxRelayResult(2, 0),
                    2 => new OutboxRelayResult(0, 1),
                    3 => new OutboxRelayResult(0, 0),
                    default => throw new RuntimeException('Stop the test relay.'),
                };
            });
        $tester    = new CommandTester(new RelayOutboxMessages($relay));
        $startedAt = microtime(true);

        try {
            $tester->execute(['--limit' => '2', '--continuous' => true]);
            self::fail('The relay did not reach the test stop condition.');
        } catch (RuntimeException $exception) {
            self::assertSame('Stop the test relay.', $exception->getMessage());
        }

        self::assertGreaterThanOrEqual(0.45, microtime(true) - $startedAt);
        self::assertStringContainsString('Idle poll: 250 ms.', $tester->getDisplay());
        self::assertStringContainsString('Published: 2; failed: 0.', $tester->getDisplay());
        self::assertStringContainsString('Published: 0; failed: 1.', $tester->getDisplay());
        self::assertStringNotContainsString('Published: 0; failed: 0.', $tester->getDisplay());
    }
}
