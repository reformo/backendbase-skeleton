<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;

use function str_contains;
use function str_starts_with;

final class ApplicationDependencyBoundaryTest extends TestCase
{
    #[Test]
    public function applicationCodeDoesNotDependOnInfrastructure(): void
    {
        $application = ArchitectureDependencies::select(
            ArchitectureDependencies::source(),
            static fn (string $file): bool => str_contains($file, '/Application/'),
        );
        $violations  = ArchitectureDependencies::violations(
            $application,
            static fn (string $_file, string $dependency): bool => str_starts_with(
                $dependency,
                'Backendbase\\Infrastructure\\',
            ),
        );

        self::assertSame([], $violations);
    }
}
