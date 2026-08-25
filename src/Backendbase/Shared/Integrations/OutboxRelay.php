<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Operation\OutboxRelayResult;

interface OutboxRelay
{
    public function relay(int $limit): OutboxRelayResult;
}
