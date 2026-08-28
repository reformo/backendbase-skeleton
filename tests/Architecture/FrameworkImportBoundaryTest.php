<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;
use Tests\Architecture\Support\BoundedContextDependencies;

use function str_contains;

final class FrameworkImportBoundaryTest extends TestCase
{
    private const array FRAMEWORK_PREFIXES = [
        'Aws\\',
        'CuyZ\\',
        'DI\\',
        'Doctrine\\',
        'GuzzleHttp\\',
        'Kreait\\',
        'Laminas\\',
        'Lcobucci\\',
        'Monolog\\',
        'PhpAmqpLib\\',
        'Psr\\Http\\',
        'Redis',
        'Redislabs\\',
        'Scienta\\',
        'Slim\\',
        'Symfony\\',
    ];

    #[Test]
    public function businessLayersDoNotImportFrameworks(): void
    {
        $violations = ArchitectureDependencies::prefixViolations(
            BoundedContextDependencies::businessLayers(),
            self::FRAMEWORK_PREFIXES,
        );

        self::assertSame([], $violations);
    }

    #[Test]
    public function sharedCoreDoesNotImportContainerOrCollectionFrameworks(): void
    {
        $sharedCore = ArchitectureDependencies::select(
            ArchitectureDependencies::shared(),
            static fn (string $file): bool => str_contains($file, '/Shared/CQRS/')
                || str_contains($file, '/Shared/Domain/')
                || str_contains($file, '/Shared/Services/EventManager/'),
        );
        $violations = ArchitectureDependencies::prefixViolations(
            $sharedCore,
            ['DI\\', 'Doctrine\\', 'Psr\\Container\\'],
        );

        self::assertSame([], $violations);
    }
}
