<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Primitives\Notification\Notification;
use Backendbase\Shared\Primitives\Notification\StackNotification;
use Override;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

class StackNotifier implements Notify
{
    public const string TYPE = 'stack';
    /** @var Notify[] */
    private array $notifiers = [];

    public function __construct(private LoggerInterface $logger)
    {
        $this->logger->debug('StackNotifier: initialized');
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function add(Notify $notifier): void
    {
        $this->logger->debug('StackNotifier: notifier added: ' . $notifier::class);

        $this->notifiers[$notifier->type()] = $notifier;
    }

    /**
     * @param StackNotification $params
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function notify(Notification $params): array
    {
        $this->logger->debug('StackNotifier: new notification');

        $statuses = [];

        foreach ($params->notifications() as $notification) {
            $notifier = $this->notifiers[$notification->type()] ?? null;
            $this->logger->debug('StackNotifier: notifier target: ' . ($notifier ? $notifier::class : 'no-notifier' ) . ' for type: ' . $notification->type());

            if ($notifier === null) {
                throw new UnexpectedValueException(
                    'No notification provider is registered for ' . $notification->type() . '.',
                );
            }

            $notifierStatus                  = $notifier->notify($notification);
            $statuses[$notification->type()] = $notifierStatus;
        }

        return $statuses;
    }

    /** @return array<int, mixed> */
    #[Override]
    public function getClient(): array
    {
        $clients = [];
        foreach ($this->notifiers as $notifier) {
            $clients[] = $notifier->getClient();
        }

        return $clients;
    }
}
