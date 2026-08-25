<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Primitives\Notification\Notification;

interface Notify
{
    /** @return array<string, mixed> */
    public function notify(Notification $params): array;

    public function getClient(): mixed;

    public function type(): string;
}
