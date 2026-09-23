<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

final readonly class PushPlatformOptions
{
    /**
     * @param array<string, mixed> $android
     * @param array<string, mixed> $apns
     */
    public function __construct(private array $android = [], private array $apns = [])
    {
    }

    /** @return array<string, mixed> */
    public function android(): array
    {
        return $this->android;
    }

    /** @return array<string, mixed> */
    public function apns(): array
    {
        return $this->apns;
    }
}
