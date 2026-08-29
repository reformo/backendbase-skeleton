<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;

use function str_contains;

final class SharedDependencyBoundaryTest extends TestCase
{
    #[Test]
    public function sharedDoesNotDependOnApplicationModules(): void
    {
        $violations = ArchitectureDependencies::prefixViolations(
            ArchitectureDependencies::shared(),
            ['Backendbase\\Application\\', 'Backendbase\\Domain\\', 'Backendbase\\Infrastructure\\'],
        );

        self::assertSame([], $violations);
    }

    #[Test]
    public function containerAwareImplementationsDoNotRemainInShared(): void
    {
        $violations = [];
        foreach (ArchitectureDependencies::shared() as $file => $_dependencies) {
            if (! str_contains($file, 'ContainerAware')) {
                continue;
            }

            $violations[] = $file;
        }

        self::assertSame([], $violations);
    }
}
