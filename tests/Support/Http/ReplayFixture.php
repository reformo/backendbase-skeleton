<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use Backendbase\Infrastructure\Configuration\Idempotency\ReplaySettings;
use Backendbase\Shared\Services\Settings;
use Doctrine\DBAL\Connection;

use function base64_encode;
use function str_repeat;

final class ReplayFixture
{
    public static function schema(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE http_request_replay (request_hash CHAR(64) PRIMARY KEY, fingerprint CHAR(64) NOT NULL, response TEXT NULL, created_at TEXT NOT NULL, expires_at TEXT NULL)');
        $connection->executeStatement('CREATE INDEX http_request_replay_expiry_idx ON http_request_replay (expires_at)');
    }

    public static function settings(): ReplaySettings
    {
        return new ReplaySettings(new Settings(['idempotency' => ['key' => base64_encode(str_repeat('i', 32))]]));
    }
}
