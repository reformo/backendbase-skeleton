<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

use function get_object_vars;

class StackNotification implements Notification
{
    /** @var array<int, Notification> */
    private array $notifications = [];
    private const string TYPE    = 'stack';

    public function addNotification(Notification $notification): self
    {
        $this->notifications[] = $notification;

        return $this;
    }

    /** @return array<int, Notification> */
    public function notifications(): array
    {
        return $this->notifications;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
