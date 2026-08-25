<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Operation\IntegrationMessageLogCleanupResult;

interface IntegrationMessageLogCleaner
{
    public function clean(int $retentionDays, int $limit): IntegrationMessageLogCleanupResult;
}
