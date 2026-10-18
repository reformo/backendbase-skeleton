<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Console\Operations;

use Backendbase\Infrastructure\Adapters\Console\Operations\CleanupRequestReplays;
use Backendbase\Shared\Idempotency\RequestReplayStore;
use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanupRequestReplaysTest extends TestCase
{
    public function testTheRegisteredCommandReportsItsBoundedCleanup(): void
    {
        $store   = $this->createMock(RequestReplayStore::class);
        $cleanup = $store->expects(self::once());
        $cleanup->method('deleteExpired');
        $cleanup->willReturn(500);
        $builder  = new ContainerBuilder();
        $commands = require 'config/commands.php';
        self::assertContains(CleanupRequestReplays::class, $commands($builder));
        $builder->addDefinitions([RequestReplayStore::class => $store]);
        $container = $builder->build();
        $command   = $container->get(CleanupRequestReplays::class);
        self::assertInstanceOf(CleanupRequestReplays::class, $command);
        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute([]));
        self::assertSame("Deleted request responses: 500.\n", $tester->getDisplay());
    }
}
