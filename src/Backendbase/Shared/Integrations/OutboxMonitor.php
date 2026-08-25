<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

interface OutboxMonitor
{
    public function status(): OutboxStatus;
}
