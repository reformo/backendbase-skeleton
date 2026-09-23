<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Backendbase\Domain\ExampleCatalog\ServiceProvider as ExampleCatalogServiceProvider;
use Backendbase\Domain\IdentityAndAccess\ServiceProvider as IdentityAndAccessServiceProvider;
use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryHandler;
use PHPUnit\Framework\Attributes\Test;
use Tests\Architecture\Support\ArchitectureDependencies;
use Tests\Architecture\Support\AttributeTargets;
use Tests\TestCase;

use function array_keys;
use function array_map;
use function array_merge;
use function is_a;
use function sort;
use function str_contains;

final class RegistryHandlerBoundaryTest extends TestCase
{
    #[Test]
    public function everyCommandAndQueryUsesAResolvableRegistryHandler(): void
    {
        $mappings            = array_merge(
            ExampleCatalogServiceProvider::getHandlers(),
            IdentityAndAccessServiceProvider::getHandlers(),
        );
        $contracts           = self::contractClasses();
        $registeredContracts = array_keys($mappings);
        sort($contracts);
        sort($registeredContracts);

        self::assertSame($contracts, $registeredContracts);
        self::assertSame([], AttributeTargets::forAttribute(CQRSHandler::class));

        $container = $this->getAppInstance()->getContainer();
        self::assertNotNull($container);

        foreach ($mappings as $contract => $handler) {
            $isCommand = is_a($contract, Command::class, true);
            self::assertTrue($isCommand || is_a($contract, Query::class, true), $contract);
            $expectedInterface = $isCommand ? CommandHandler::class : QueryHandler::class;
            self::assertTrue(is_a($handler, $expectedInterface, true), $contract);
            self::assertTrue($container->has($handler), $contract);
            self::assertIsObject($container->get($handler), $contract);
        }
    }

    /** @return list<string> */
    private static function contractClasses(): array
    {
        $contracts = ArchitectureDependencies::select(
            ArchitectureDependencies::domain(),
            static fn (string $file): bool => str_contains($file, '/Contracts/Command/')
                || str_contains($file, '/Contracts/Query/'),
        );

        return array_map(ArchitectureDependencies::className(...), array_keys($contracts));
    }
}
