<?php

declare(strict_types=1);

namespace Tests\Shared\Console;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandTest extends TestCase
{
    #[Test]
    public function itWritesTimestampedMessagesToTheLogAndConsole(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('console-test');
        $logger->pushHandler($handler);
        $tester = new CommandTester(new TestLoggingCommand($logger));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('CLI/test-log - completed', $tester->getDisplay());
        self::assertTrue($handler->hasDebugThatContains('CLI/test-log - completed'));
    }
}
