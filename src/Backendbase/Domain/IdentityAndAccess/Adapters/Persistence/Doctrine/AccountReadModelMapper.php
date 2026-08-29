<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\AccountListItem;
use Backendbase\Shared\Helpers\DateTimeImmutable as DateTimeImmutableFactory;
use DateTimeImmutable;
use Throwable;
use UnexpectedValueException;

use function array_key_exists;
use function array_unique;
use function array_values;
use function is_string;

final class AccountReadModelMapper
{
    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<AccountListItem>
     */
    public static function list(array $rows): array
    {
        /** @var array<string, array{email: string, createdAt: DateTimeImmutable, privilegeSlugs: list<string>}> $accounts */
        $accounts = [];
        foreach ($rows as $row) {
            $uuid = self::string($row['uuid'] ?? null, 'uuid');
            if (! array_key_exists($uuid, $accounts)) {
                $accounts[$uuid] = [
                    'email' => self::string($row['email'] ?? null, 'email'),
                    'createdAt' => self::date($row['created_at'] ?? null, 'created_at'),
                    'privilegeSlugs' => [],
                ];
            }

            $privilegeSlug = $row['privilege_slug'] ?? null;
            if ($privilegeSlug === null) {
                continue;
            }

            $accounts[$uuid]['privilegeSlugs'][] = self::string($privilegeSlug, 'privilege_slug');
        }

        $items = [];
        foreach ($accounts as $uuid => $account) {
            $items[] = new AccountListItem(
                $uuid,
                $account['email'],
                array_values(array_unique($account['privilegeSlugs'])),
                $account['createdAt'],
            );
        }

        return $items;
    }

    private static function date(mixed $value, string $field): DateTimeImmutable
    {
        try {
            return DateTimeImmutableFactory::create(self::string($value, $field));
        } catch (Throwable $exception) {
            throw new UnexpectedValueException('The persisted ' . $field . ' value is invalid.', 0, $exception);
        }
    }

    private static function string(mixed $value, string $field): string
    {
        if (! is_string($value) || $value === '') {
            throw new UnexpectedValueException('The persisted ' . $field . ' value must be a non-empty string.');
        }

        return $value;
    }
}
