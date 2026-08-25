<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;
use Tests\Architecture\Support\BoundedContextDependencies;

use function str_contains;
use function str_starts_with;

final class AdapterDirectionTest extends TestCase
{
    #[Test]
    public function businessLayersDoNotDependOnAdapters(): void
    {
        $violations = ArchitectureDependencies::violations(
            BoundedContextDependencies::businessLayers(),
            static fn (string $file, string $dependency): bool => str_starts_with(
                $dependency,
                'Backendbase\\Domain\\',
            ) && str_contains($dependency, '\\Adapters\\'),
        );

        self::assertSame([], $violations);
    }
}
