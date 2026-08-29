<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;

use function str_contains;

final class SharedCoreFrameworkBoundaryTest extends TestCase
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
        'Psr\\',
        'Redis',
        'Redislabs\\',
        'Scienta\\',
        'Slim\\',
        'Symfony\\',
    ];

    #[Test]
    public function sharedCoreDoesNotImportFrameworks(): void
    {
        $sharedCore = ArchitectureDependencies::select(
            ArchitectureDependencies::shared(),
            static fn (string $file): bool => ! self::isExplicitFrameworkBoundary($file),
        );
        $violations = ArchitectureDependencies::prefixViolations(
            $sharedCore,
            self::FRAMEWORK_PREFIXES,
        );

        self::assertSame([], $violations);
    }

    private static function isExplicitFrameworkBoundary(string $file): bool
    {
        return str_contains($file, '/Shared/Console/')
            || str_contains($file, '/Shared/Http/')
            || str_contains($file, '/Shared/Migrations/')
            || str_contains($file, '/Shared/Persistence/Doctrine')
            || str_contains($file, '/Shared/Services/ObjectMapper.php');
    }
}
