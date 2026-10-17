<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Schema;

use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Name;

use function array_map;
use function implode;
use function preg_match;
use function preg_quote;
use function trim;

final readonly class MappedTableFilter
{
    private string $expression;

    /** @param list<string> $tableNames */
    public function __construct(array $tableNames, bool $includeMigrationMetadata = false)
    {
        if ($includeMigrationMetadata) {
            $tableNames[] = 'doctrine_migration_versions';
        }

        $names            = array_map(static fn (string $name): string => preg_quote(trim($name, '`"'), '/'), $tableNames);
        $this->expression = '/\A[`"]?(?:' . implode('|', $names) . ')[`"]?\z/D';
    }

    /** @param string|AbstractAsset<covariant Name> $asset */
    public function __invoke(string|AbstractAsset $asset): bool
    {
        $name = $asset instanceof AbstractAsset ? $asset->getName() : $asset;

        return preg_match($this->expression, $name) === 1;
    }

    public function expression(): string
    {
        return $this->expression;
    }
}
