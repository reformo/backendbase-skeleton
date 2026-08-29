<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Queue;

use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Integrations\Operation\QueueMessageHandlingOutcome;
use Backendbase\Shared\Integrations\QueueMessageFailurePolicy;
use Backendbase\Shared\Persistence\ExternalEffectInbox;
use Backendbase\Shared\Persistence\ExternalEffectInProgress;
use Backendbase\Shared\Persistence\ExternalEffectOutcomeUnknown;
use Backendbase\Shared\Primitives\Notification\EmailNotification;
use Backendbase\Shared\Primitives\Notification\StackNotification;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class NotificationMessageProcessor
{
    private const string EVENT_NAME = 'Notification_Email';

    public function __construct(
        private Notify $notifier,
        private ExternalEffectInbox $externalEffectInbox,
        private QueueMessageFailurePolicy $failurePolicy,
        private LoggerInterface $logger,
    ) {
    }

    public function process(Message $message): QueueMessageHandlingOutcome
    {
        $consumerName = $message->destination();
        $messageId    = $message->id();
        try {
            [$validatedConsumerName, $validatedMessageId] = $this->metadata($consumerName, $messageId);
        } catch (UnexpectedValueException $exception) {
            $this->logger->error('Notification queue message is invalid.', [
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);

            return QueueMessageHandlingOutcome::REJECT;
        }

        try {
            $notification = $this->notification($message->body());
            $this->externalEffectInbox->processOnce(
                $validatedConsumerName,
                $validatedMessageId,
                self::EVENT_NAME,
                function () use ($notification): void {
                    $this->notifier->notify($notification);
                },
            );
            $this->failurePolicy->succeeded($validatedConsumerName, $validatedMessageId);

            return QueueMessageHandlingOutcome::ACKNOWLEDGE;
        } catch (ExternalEffectInProgress $exception) {
            $this->logger->info('Notification delivery is already in progress.', [
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);

            return QueueMessageHandlingOutcome::RETRY;
        } catch (ExternalEffectOutcomeUnknown $exception) {
            $this->logger->critical('Notification delivery outcome is unknown.', [
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);

            return $this->failurePolicy->permanentFailure(
                $validatedConsumerName,
                $validatedMessageId,
                $exception::class,
            );
        } catch (JsonException | UnexpectedValueException $exception) {
            $this->logger->error('Notification queue message is invalid.', [
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);

            return $this->failurePolicy->permanentFailure(
                $validatedConsumerName,
                $validatedMessageId,
                $exception::class,
            );
        } catch (Throwable $exception) {
            $this->logger->error('Notification queue message processing failed.', [
                'exception' => $exception::class,
                'message_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);

            return $this->failurePolicy->transientFailure(
                $validatedConsumerName,
                $validatedMessageId,
                $exception::class,
            );
        }
    }

    /** @return array{string, string} */
    private function metadata(string|null $consumerName, string|null $messageId): array
    {
        if (! is_string($consumerName) || $consumerName === '') {
            throw new UnexpectedValueException('The notification consumer name is missing.');
        }

        if (! is_string($messageId) || $messageId === '') {
            throw new UnexpectedValueException('The notification message ID is missing.');
        }

        return [$consumerName, $messageId];
    }

    private function notification(string $messageBody): StackNotification
    {
        if ($messageBody === '') {
            throw new UnexpectedValueException('The notification message body is missing.');
        }

        $htmlBody = json_decode($messageBody, true, 512, JSON_THROW_ON_ERROR);
        if (! is_string($htmlBody)) {
            throw new UnexpectedValueException('The notification message body must contain a JSON string.');
        }

        $notification = new EmailNotification()->setHtmlBody($htmlBody);

        return new StackNotification()->addNotification($notification);
    }
}
