<?php

declare(strict_types=1);

use Backendbase\Infrastructure\Adapters\Time\SystemClock;
use Backendbase\Shared\Time\Clock;
use DI\ContainerBuilder;

use function DI\autowire;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([Clock::class => autowire(SystemClock::class)]);
};
