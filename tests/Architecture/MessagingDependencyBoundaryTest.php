<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;

use function str_contains;
use function str_starts_with;

final class MessagingDependencyBoundaryTest extends TestCase
{
    #[Test]
    public function messagingDependsOnlyOnSharedItsOwnTypesAndNativePhpTypes(): void
    {
        $messaging  = ArchitectureDependencies::select(
            ArchitectureDependencies::source(),
            static fn (string $file): bool => str_starts_with($file, 'src/Backendbase/Infrastructure/Messaging/'),
        );
        $violations = ArchitectureDependencies::violations(
            $messaging,
            static fn (string $_file, string $dependency): bool => str_contains($dependency, '\\')
                && $dependency !== 'Backendbase\\Infrastructure\\Messaging'
                && ! str_starts_with($dependency, 'Backendbase\\Shared\\')
                && ! str_starts_with($dependency, 'Backendbase\\Infrastructure\\Messaging\\'),
        );

        self::assertNotEmpty($messaging);
        self::assertSame([], $violations);
    }
}
