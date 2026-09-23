<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Console\Queue;

use Backendbase\Infrastructure\Adapters\Console\Queue\ShowOutboxStatus;
use Backendbase\Shared\Integrations\OutboxMonitor;
use Backendbase\Shared\Integrations\OutboxStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\Time\FrozenClock;

final class ShowOutboxStatusTest extends TestCase
{
    #[Test]
    public function itFailsWhenAPendingMessageIsTooOldWithoutRetries(): void
    {
        $monitor = $this->createStub(OutboxMonitor::class);
        $monitor->method('status')->willReturn(new OutboxStatus(1, 0, '2026-09-24 09:54:59.999999'));
        $tester = new CommandTester(new ShowOutboxStatus(
            $monitor,
            new FrozenClock(new DateTimeImmutable('2026-09-24T10:00:00+00:00')),
        ));

        $exitCode = $tester->execute(['--max-pending-age' => '300']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('exceeds the maximum age', $tester->getDisplay());
    }

    #[Test]
    public function itSucceedsWhenNoPendingMessageExceedsTheMaximumAge(): void
    {
        $monitor = $this->createStub(OutboxMonitor::class);
        $monitor->method('status')->willReturn(new OutboxStatus(1, 0, '2026-09-24 09:55:00.000000'));
        $tester = new CommandTester(new ShowOutboxStatus(
            $monitor,
            new FrozenClock(new DateTimeImmutable('2026-09-24T10:00:00+00:00')),
        ));

        $exitCode = $tester->execute(['--max-pending-age' => '300']);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function itRejectsAnInvalidMaximumPendingAge(): void
    {
        $monitor = $this->createStub(OutboxMonitor::class);
        $monitor->method('status')->willReturn(new OutboxStatus(0, 0, null));
        $tester = new CommandTester(new ShowOutboxStatus(
            $monitor,
            new FrozenClock(new DateTimeImmutable('2026-09-24T10:00:00+00:00')),
        ));

        $exitCode = $tester->execute(['--max-pending-age' => 'invalid']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('positive integer', $tester->getDisplay());
    }

    #[Test]
    public function itFailsWhenMessagesHaveRetries(): void
    {
        $monitor = $this->createStub(OutboxMonitor::class);
        $monitor->method('status')->willReturn(new OutboxStatus(0, 1, null));
        $tester = new CommandTester(new ShowOutboxStatus(
            $monitor,
            new FrozenClock(new DateTimeImmutable('2026-09-24T10:00:00+00:00')),
        ));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Oldest pending: none', $tester->getDisplay());
    }
}
