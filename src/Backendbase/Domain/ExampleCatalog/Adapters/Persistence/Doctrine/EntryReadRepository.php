<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository as EntryReadRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryIdByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryGroupPage;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

use function array_map;

final readonly class EntryReadRepository implements EntryReadRepositoryContract
{
    private const string TABLE = 'example_table';

    public function __construct(private Connection $connection)
    {
    }

    public function getEntryIdByCriteria(GetEntryIdByCriteria $query): string|null
    {
        [$targetCondition, $parameters] = self::targetCondition($query->type(), $query->typeTargetId());
        $parameters['lookupGroup']      = $query->group();
        $parameters['lookupKey']        = $query->key();
        $entryId                        = $this->connection->fetchOne(
            'SELECT uuid FROM ' . self::TABLE
            . ' WHERE type = :type AND ' . $targetCondition
            . ' AND lookup_group = :lookupGroup AND lookup_key = :lookupKey'
            . ' AND deleted_at IS NULL LIMIT 1',
            $parameters,
        );

        return $entryId === false ? null : EntryReadModelMapper::string($entryId, 'uuid');
    }

    public function getEntryByCriteria(GetEntryByCriteria $query): EntryDetails|null
    {
        [$targetCondition, $parameters] = self::targetCondition($query->type(), $query->typeTargetId());
        $parameters['lookupGroup']      = $query->group();
        $parameters['lookupKey']        = $query->key();
        $row                            = $this->connection->fetchAssociative(
            'SELECT uuid, type, type_target_id, lookup_group, lookup_key, lookup_value, details, is_active,'
            . ' COALESCE(updated_at, created_at) AS updated_at, created_at FROM ' . self::TABLE
            . ' WHERE type = :type AND ' . $targetCondition
            . ' AND lookup_group = :lookupGroup AND lookup_key = :lookupKey'
            . ' AND deleted_at IS NULL LIMIT 1',
            $parameters,
        );
        if ($row === false) {
            return null;
        }

        return EntryReadModelMapper::details($row);
    }

    public function getEntryGroupsByType(GetEntryGroupsByType $query): EntryGroupPage
    {
        [$targetCondition, $parameters] = self::targetCondition($query->type(), $query->typeTargetId());
        $where                          = 'type = :type AND ' . $targetCondition . ' AND deleted_at IS NULL';
        $total                          = EntryReadModelMapper::integer(
            $this->connection->fetchOne('SELECT COUNT(DISTINCT lookup_group) FROM ' . self::TABLE . ' WHERE ' . $where, $parameters),
            'total',
        );
        $pagination                     = $query->pagination();
        $parameters['pageSize']         = $pagination->pageSize();
        $parameters['offset']           = $pagination->getOffset();
        $groups                         = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT lookup_group FROM ' . self::TABLE
            . ' WHERE ' . $where . ' ORDER BY lookup_group ASC LIMIT :pageSize OFFSET :offset',
            $parameters,
            ['pageSize' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );

        $items = array_map(
            static fn (mixed $group): string => EntryReadModelMapper::string($group, 'lookup_group'),
            $groups,
        );

        return new EntryGroupPage($items, $total);
    }

    public function getEntriesByGroup(GetEntriesByGroup $query): EntryPage
    {
        [$targetCondition, $parameters] = self::targetCondition($query->type(), $query->typeTargetId());
        $parameters['lookupGroup']      = $query->group();
        $where                          = 'type = :type AND ' . $targetCondition
            . ' AND lookup_group = :lookupGroup AND deleted_at IS NULL';
        $total                          = EntryReadModelMapper::integer(
            $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE ' . $where, $parameters),
            'total',
        );
        if ($total === 0) {
            return new EntryPage([], 0);
        }

        $pagination             = $query->pagination();
        $parameters['pageSize'] = $pagination->pageSize();
        $parameters['offset']   = $pagination->getOffset();
        $rows                   = $this->connection->fetchAllAssociative(
            'SELECT uuid, lookup_key, lookup_value, details, is_active, created_at FROM ' . self::TABLE
            . ' WHERE ' . $where . ' ORDER BY created_at ASC, id ASC LIMIT :pageSize OFFSET :offset',
            $parameters,
            ['pageSize' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );
        $items                  = array_map(EntryReadModelMapper::listItem(...), $rows);

        return new EntryPage($items, $total);
    }

    /** @return array{string, array<string, int|string>} */
    private static function targetCondition(EntryType $type, int|null $typeTargetId): array
    {
        $parameters = ['type' => $type->value];
        if ($typeTargetId === null) {
            return ['type_target_id IS NULL', $parameters];
        }

        $parameters['typeTargetId'] = $typeTargetId;

        return ['type_target_id = :typeTargetId', $parameters];
    }
}
