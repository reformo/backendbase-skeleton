<?php

declare(strict_types=1);

namespace Tests\Architecture\Adapters;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\AdapterDependencies;
use Tests\Architecture\Support\ArchitectureDependencies;

use function array_intersect_key;
use function str_starts_with;

final class AdapterClassificationTest extends TestCase
{
    #[Test]
    public function allProductionAdaptersHaveAnExplicitDirection(): void
    {
        $source = ArchitectureDependencies::source();

        self::assertSame([], AdapterDependencies::unclassified($source));
        self::assertSame([], array_intersect_key(AdapterDependencies::inbound($source), AdapterDependencies::outbound($source)));
    }

    #[Test]
    public function sharedDiagnosticsCannotBridgeAdapterDirections(): void
    {
        $source = ArchitectureDependencies::source();
        $file   = 'src/Backendbase/Infrastructure/Adapters/Diagnostics/SafeExceptionContext.php';

        self::assertSame([], ArchitectureDependencies::violations(
            [$file => $source[$file]],
            static fn (string $_file, string $dependency): bool => $dependency !== 'Backendbase\\Infrastructure\\Adapters\\Diagnostics'
                && (str_starts_with($dependency, 'Backendbase\\Domain\\') || str_starts_with($dependency, 'Backendbase\\Infrastructure\\')),
        ));
    }

    #[Test]
    #[DataProvider('unknownAdapters')]
    public function newCategoriesCannotEscapeClassification(string $file): void
    {
        self::assertSame([$file], AdapterDependencies::unclassified([$file => []]));
    }

    /** @return iterable<string, array{string}> */
    public static function unknownAdapters(): iterable
    {
        yield 'context' => ['src/Backendbase/Domain/IdentityAndAccess/Adapters/NewCategory/Adapter.php'];
        yield 'infrastructure' => ['src/Backendbase/Infrastructure/Adapters/NewCategory/Adapter.php'];
    }

    #[Test]
    #[DataProvider('outboundCategories')]
    public function consoleCannotDependOnOutboundImplementations(string $category): void
    {
        $inbound    = 'src/Backendbase/Domain/IdentityAndAccess/Adapters/Console/IdentityOperator.php';
        $outbound   = 'src/Backendbase/Domain/IdentityAndAccess/Adapters/' . $category . '/Adapter.php';
        $dependency = ArchitectureDependencies::className($outbound);
        $source     = [$inbound => [$dependency], $outbound => []];
        $classes    = AdapterDependencies::classSet(AdapterDependencies::outbound($source));

        self::assertSame([$inbound . ' depends on ' . $dependency], ArchitectureDependencies::violations(
            AdapterDependencies::inbound($source),
            static fn (string $_file, string $reference): bool => isset($classes[$reference]),
        ));
    }

    #[Test]
    #[DataProvider('outboundCategories')]
    public function outboundImplementationsCannotDependOnConsole(string $category): void
    {
        $inbound    = 'src/Backendbase/Domain/IdentityAndAccess/Adapters/Console/IdentityOperator.php';
        $outbound   = 'src/Backendbase/Domain/IdentityAndAccess/Adapters/' . $category . '/Adapter.php';
        $dependency = ArchitectureDependencies::className($inbound);
        $source     = [$inbound => [], $outbound => [$dependency]];
        $classes    = AdapterDependencies::classSet(AdapterDependencies::inbound($source));

        self::assertSame([$outbound . ' depends on ' . $dependency], ArchitectureDependencies::violations(
            AdapterDependencies::outbound($source),
            static fn (string $_file, string $reference): bool => isset($classes[$reference]),
        ));
    }

    /** @return iterable<string, array{string}> */
    public static function outboundCategories(): iterable
    {
        yield 'notification' => ['Notification'];
        yield 'read model' => ['ReadModel'];
    }
}
