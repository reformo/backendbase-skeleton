<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;

final class SharedDependencyBoundaryTest extends TestCase
{
    #[Test]
    public function sharedDoesNotDependOnApplicationModules(): void
    {
        $violations = ArchitectureDependencies::prefixViolations(
            ArchitectureDependencies::shared(),
            ['Backendbase\\Domain\\', 'Backendbase\\Infrastructure\\'],
        );

        self::assertSame([], $violations);
    }
}
