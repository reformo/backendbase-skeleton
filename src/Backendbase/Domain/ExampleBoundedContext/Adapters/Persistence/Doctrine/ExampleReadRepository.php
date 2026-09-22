<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository as ExampleReadRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleDetails;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleGroupPage;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExamplePage;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

use function array_map;

final readonly class ExampleReadRepository implements ExampleReadRepositoryContract
{
    private const string TABLE = 'example_table';

    public function __construct(private Connection $connection)
    {
    }

    public function getExampleIdByCriteria(GetExampleIdByCriteria $query): string|null
    {
        [$targetCondition, $parameters] = self::targetCondition($query->type(), $query->typeTargetId());
        $parameters['lookupGroup']      = $query->group();
        $parameters['lookupKey']        = $query->key();
        $exampleId                      = $this->connection->fetchOne(
            'SELECT uuid FROM ' . self::TABLE
            . ' WHERE type = :type AND ' . $targetCondition
            . ' AND lookup_group = :lookupGroup AND lookup_key = :lookupKey'
            . ' AND deleted_at IS NULL LIMIT 1',
            $parameters,
        );

        return $exampleId === false ? null : ExampleReadModelMapper::string($exampleId, 'uuid');
    }

    public function getExampleByCriteria(GetExampleByCriteria $query): ExampleDetails|null
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

        return ExampleReadModelMapper::details($row);
    }

    public function getExampleGroupsByType(GetExampleGroupsByType $query): ExampleGroupPage
    {
        [$targetCondition, $parameters] = self::targetCondition($query->type(), $query->typeTargetId());
        $where                          = 'type = :type AND ' . $targetCondition . ' AND deleted_at IS NULL';
        $total                          = ExampleReadModelMapper::integer(
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
            static fn (mixed $group): string => ExampleReadModelMapper::string($group, 'lookup_group'),
            $groups,
        );

        return new ExampleGroupPage($items, $total);
    }

    public function getExamplesByGroup(GetExamplesByGroup $query): ExamplePage
    {
        [$targetCondition, $parameters] = self::targetCondition($query->type(), $query->typeTargetId());
        $parameters['lookupGroup']      = $query->group();
        $where                          = 'type = :type AND ' . $targetCondition
            . ' AND lookup_group = :lookupGroup AND deleted_at IS NULL';
        $total                          = ExampleReadModelMapper::integer(
            $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE ' . $where, $parameters),
            'total',
        );
        if ($total === 0) {
            return new ExamplePage([], 0);
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
        $items                  = array_map(ExampleReadModelMapper::listItem(...), $rows);

        return new ExamplePage($items, $total);
    }

    /** @return array{string, array<string, int|string>} */
    private static function targetCondition(ExampleType $type, int|null $typeTargetId): array
    {
        $parameters = ['type' => $type->value];
        if ($typeTargetId === null) {
            return ['type_target_id IS NULL', $parameters];
        }

        $parameters['typeTargetId'] = $typeTargetId;

        return ['type_target_id = :typeTargetId', $parameters];
    }
}
