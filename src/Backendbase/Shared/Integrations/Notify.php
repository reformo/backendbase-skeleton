<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\Notification;

interface Notify
{
    /**
     * Deliver one notification or a validated batch in order.
     * A batch failure carries completed results and the first failed index.
     */
    public function notify(Notification $notification): NotificationResult;
}
