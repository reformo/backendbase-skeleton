<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\Notification;

interface Notify
{
    public function notify(Notification $notification): NotificationResult;
}
