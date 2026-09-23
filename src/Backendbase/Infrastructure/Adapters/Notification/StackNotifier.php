<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Notification;

use Backendbase\Shared\Integrations\NotificationProvider;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\Operation\NotificationBatchFailed;
use Backendbase\Shared\Integrations\Operation\NotificationResult;
use Backendbase\Shared\Primitives\Notification\Notification;
use Backendbase\Shared\Primitives\Notification\StackNotification;
use Override;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

class StackNotifier implements Notify
{
    /** @var array<string, NotificationProvider> */
    private array $notifiers = [];

    public function __construct(private LoggerInterface $logger)
    {
        $logger->debug('StackNotifier: initialized');
    }

    public function add(NotificationProvider $notifier): void
    {
        $type = $notifier->type();
        if (isset($this->notifiers[$type])) {
            throw new UnexpectedValueException('A notification provider is already registered for ' . $type . '.');
        }

        $this->notifiers[$type] = $notifier;
        $logger                 = $this->logger;
        $logger->debug('StackNotifier: provider registered for ' . $type);
    }

    #[Override]
    public function notify(Notification $notification): NotificationResult
    {
        if ($notification instanceof StackNotification) {
            return $this->notifyStack($notification);
        }

        $provider = $this->providerFor($notification);

        return $provider->notify($notification);
    }

    private function notifyStack(StackNotification $stack): NotificationResult
    {
        $notifications = $stack->notifications();
        foreach ($notifications as $notification) {
            $this->providerFor($notification);
        }

        $result = NotificationResult::empty();
        foreach ($notifications as $index => $notification) {
            $result = $this->deliverInBatch($notification, $result, $index);
        }

        return $result;
    }

    private function deliverInBatch(Notification $notification, NotificationResult $completed, int $index): NotificationResult
    {
        try {
            $provider = $this->providerFor($notification);
            $result   = $provider->notify($notification);

            return $completed->merge($result);
        } catch (Throwable $exception) {
            throw new NotificationBatchFailed($completed, $index, $exception);
        }
    }

    private function providerFor(Notification $notification): NotificationProvider
    {
        $type     = $notification->type();
        $provider = $this->notifiers[$type] ?? null;
        if ($provider === null) {
            throw new UnexpectedValueException('No notification provider is registered for ' . $type . '.');
        }

        return $provider;
    }
}
