<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;
use Tests\Architecture\Support\BoundedContextDependencies;

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
}
