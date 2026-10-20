<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use Closure;
use DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_keys;
use function array_map;
use function basename;
use function file_get_contents;
use function glob;
use function json_decode;
use function preg_match_all;
use function sort;

use const JSON_THROW_ON_ERROR;

final class ProviderFactoryInventoryTest extends TestCase
{
    public function testEveryRegisteredProviderHasAnEvidenceInventory(): void
    {
        $expected = array_keys(self::inventory());
        sort($expected);
        $files = glob('config/dependencies/*.php');
        self::assertNotFalse($files);
        $actual = array_map(basename(...), $files);
        sort($actual);
        self::assertSame($expected, $actual);
        $source = file_get_contents('config/dependencies.php');
        self::assertIsString($source);
        preg_match_all("/'([a-z-]+\\.php)'/", $source, $matches);
        $registered = $matches[1];
        sort($registered);
        self::assertSame($expected, $registered);
    }

    /** @param array{factories: list<string>, branches: list<string>, evidence: list<string>} $inventory */
    #[DataProvider('providers')]
    public function testFactoryRegistrationsMatchTheirMaintainedEvidence(string $file, array $inventory): void
    {
        $factories = [];
        $builder   = $this->createMock(ContainerBuilder::class);
        $builder->expects(self::once())->method('addDefinitions')->willReturnCallback(static function (array $definitions) use ($builder, &$factories): ContainerBuilder {
            $factories = array_keys(array_filter($definitions, static fn (mixed $value): bool => $value instanceof Closure));
            sort($factories);

            return $builder;
        });
        $provider = require 'config/dependencies/' . $file;
        $provider($builder);
        self::assertSame($inventory['factories'], $factories, 'Update the branch evidence when changing a runtime factory.');
        self::assertNotEmpty($inventory['branches']);
        self::assertNotEmpty($inventory['evidence']);
        foreach ($inventory['evidence'] as $evidence) {
            self::assertFileExists($evidence);
        }
    }

    /** @return iterable<string, array{string, array{factories: list<string>, branches: list<string>, evidence: list<string>}}> */
    public static function providers(): iterable
    {
        foreach (self::inventory() as $file => $inventory) {
            yield $file => [$file, $inventory];
        }
    }

    /** @return array<string, array{factories: list<string>, branches: list<string>, evidence: list<string>}> */
    private static function inventory(): array
    {
        $source = file_get_contents(__DIR__ . '/provider-factories.json');
        self::assertIsString($source);

        return json_decode($source, true, flags: JSON_THROW_ON_ERROR);
    }
}
