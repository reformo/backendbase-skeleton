<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\AdapterDependencies;
use Tests\Architecture\Support\ArchitectureDependencies;

final class InboundAdapterDependencyBoundaryTest extends TestCase
{
    #[Test]
    public function inboundAdaptersDoNotDependOnOutboundAdapters(): void
    {
        $outboundClasses = AdapterDependencies::classSet(AdapterDependencies::outbound());
        $violations      = ArchitectureDependencies::violations(
            AdapterDependencies::inbound(),
            static fn (string $_file, string $dependency): bool => isset($outboundClasses[$dependency]),
        );

        self::assertSame([], $violations);
    }
}
