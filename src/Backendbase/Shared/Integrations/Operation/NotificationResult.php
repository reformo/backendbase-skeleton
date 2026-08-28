<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

use function array_key_exists;
use function array_replace;

final readonly class NotificationResult
{
    /** @param array<string, string|null> $messageIds */
    private function __construct(private array $messageIds)
    {
    }

    public static function delivered(string $notificationType, string|null $messageId): self
    {
        return new self([$notificationType => $messageId]);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function merge(self $result): self
    {
        return new self(array_replace($this->messageIds, $result->messageIds));
    }

    public function has(string $notificationType): bool
    {
        return array_key_exists($notificationType, $this->messageIds);
    }

    public function messageId(string $notificationType): string|null
    {
        return $this->messageIds[$notificationType] ?? null;
    }
}
