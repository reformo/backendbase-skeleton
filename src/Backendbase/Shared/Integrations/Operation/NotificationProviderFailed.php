<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

use RuntimeException;
use Throwable;

final class NotificationProviderFailed extends RuntimeException
{
    public function __construct(string $type, Throwable $previous)
    {
        parent::__construct('Notification delivery failed for ' . $type . '.', 0, $previous);
    }
}
