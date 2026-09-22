<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Doctrine\DBAL\Connection;
use LogicException;

final readonly class DoctrineAccountLock
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param callable(): TResult $operation
     *
     * @return TResult
     *
     * @template TResult
     */
    public function forEmail(string $email, callable $operation): mixed
    {
        return $this->execute('email', $email, $operation);
    }

    /**
     * @param callable(): TResult $operation
     *
     * @return TResult
     *
     * @template TResult
     */
    public function forAccount(AccountId $accountId, callable $operation): mixed
    {
        $identity = $accountId->toString();

        return $this->execute('uuid', $identity, $operation);
    }

    /**
     * @param 'email'|'uuid'      $column
     * @param callable(): TResult $operation
     *
     * @return TResult
     *
     * @template TResult
     */
    private function execute(string $column, string $identity, callable $operation): mixed
    {
        if ($this->connection->isTransactionActive()) {
            throw new LogicException('Account locking requires an outermost transaction with a fresh database snapshot.');
        }

        $lockedOperation = static function (Connection $connection) use ($column, $identity, $operation): mixed {
            // The unchanged assignment acquires a write lock on both MySQL and SQLite.
            $connection->executeStatement(
                'UPDATE example_accounts SET uuid = uuid WHERE ' . $column . ' = :identity AND deleted_at IS NULL',
                ['identity' => $identity],
            );

            return $operation();
        };

        return $this->connection->transactional($lockedOperation);
    }
}
