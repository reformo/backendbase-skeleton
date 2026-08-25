<?php

declare(strict_types=1);

namespace Backendbase\Shared\Migrations;

use Doctrine\Migrations\AbstractMigration;

/**
 * Base migration for Backendbase MySQL services.
 *
 * DDL (CREATE/ALTER/DROP) implicitly commits on MySQL, which breaks Doctrine
 * savepoints when all_or_nothing is enabled. Non-transactional migrations avoid
 * SAVEPOINT DOCTRINE_* errors in CI/CD migrate steps.
 */
abstract class BackendbaseAbstractMigration extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }
}
