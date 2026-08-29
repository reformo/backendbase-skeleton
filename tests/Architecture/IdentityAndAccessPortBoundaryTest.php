<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;
use Tests\Architecture\Support\PhpDependencyScanner;

use function dirname;

final class IdentityAndAccessPortBoundaryTest extends TestCase
{
    #[Test]
    public function httpInputsDoNotDependOnTheJwtAdapter(): void
    {
        $projectRoot  = dirname(__DIR__, 2);
        $dependencies = PhpDependencyScanner::dependenciesByFile(
            $projectRoot,
            'src/Backendbase/Infrastructure/UseCase',
        );
        $violations   = ArchitectureDependencies::violations(
            $dependencies,
            static fn (string $_file, string $dependency): bool => $dependency === Jwt::class,
        );

        self::assertSame([], $violations);
    }
}
