<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\AdapterDependencies;
use Tests\Architecture\Support\ArchitectureDependencies;

final class OutboundAdapterDependencyBoundaryTest extends TestCase
{
    #[Test]
    public function outboundAdaptersDoNotDependOnInboundAdapters(): void
    {
        $inboundClasses = AdapterDependencies::classSet(AdapterDependencies::inbound());
        $violations     = ArchitectureDependencies::violations(
            AdapterDependencies::outbound(),
            static fn (string $_file, string $dependency): bool => isset($inboundClasses[$dependency]),
        );

        self::assertSame([], $violations);
    }
}
