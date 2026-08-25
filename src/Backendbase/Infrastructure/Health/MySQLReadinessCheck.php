<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Health;

use Backendbase\Shared\Health\ReadinessCheck;
use Doctrine\DBAL\Connection;
use Override;
use UnexpectedValueException;

final readonly class MySQLReadinessCheck implements ReadinessCheck
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return non-empty-string */
    #[Override]
    public function name(): string
    {
        return 'mysql';
    }

    #[Override]
    public function check(): void
    {
        $result = $this->connection->executeQuery('SELECT 1')->fetchOne();
        if ((string) $result !== '1') {
            throw new UnexpectedValueException('The MySQL readiness query returned an invalid result.');
        }
    }
}
