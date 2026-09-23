<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ArchitectureDependencies;

final class SharedFrameworkBoundaryTest extends TestCase
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
    public function sharedDoesNotImportFrameworks(): void
    {
        $violations = ArchitectureDependencies::prefixViolations(
            ArchitectureDependencies::shared(),
            self::FRAMEWORK_PREFIXES,
        );

        self::assertSame([], $violations);
    }
}
