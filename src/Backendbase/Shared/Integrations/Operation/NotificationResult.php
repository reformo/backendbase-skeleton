<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

use LogicException;

use function array_merge;
use function count;

final readonly class NotificationResult
{
    /** @param list<NotificationDelivery> $deliveries */
    private function __construct(private array $deliveries)
    {
    }

    public static function delivered(string $notificationType, string|null $messageId): self
    {
        return new self([new NotificationDelivery($notificationType, $messageId)]);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function merge(self $result): self
    {
        $deliveries = $result->deliveries();

        return new self(array_merge($this->deliveries, $deliveries));
    }

    public function has(string $notificationType): bool
    {
        return $this->messageIds($notificationType) !== [];
    }

    public function messageId(string $notificationType): string|null
    {
        $messageIds = $this->messageIds($notificationType);
        if (count($messageIds) > 1) {
            throw new LogicException('More than one notification was delivered for ' . $notificationType . '.');
        }

        return $messageIds[0] ?? null;
    }

    /** @return list<string|null> */
    public function messageIds(string $notificationType): array
    {
        $messageIds = [];
        foreach ($this->deliveries as $delivery) {
            if ($delivery->type() !== $notificationType) {
                continue;
            }

            $messageIds[] = $delivery->messageId();
        }

        return $messageIds;
    }

    /** @return list<NotificationDelivery> */
    public function deliveries(): array
    {
        return $this->deliveries;
    }
}
