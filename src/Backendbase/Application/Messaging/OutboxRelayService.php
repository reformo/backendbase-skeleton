<?php

declare(strict_types=1);

namespace Backendbase\Application\Messaging;

use Backendbase\Shared\Integrations\Operation\OutboxRelayResult;
use Backendbase\Shared\Integrations\OutboxPublisher;
use Backendbase\Shared\Integrations\OutboxRelay;
use Backendbase\Shared\Persistence\OutboxMessageStore;
use Backendbase\Shared\Time\Clock;

final readonly class OutboxRelayService implements OutboxRelay
{
    private OutboxPublication $publication;

    public function __construct(
        OutboxMessageStore $messageStore,
        private OutboxPublisher $publisher,
        Clock $clock,
    ) {
        $this->publication = new OutboxPublication($messageStore, $clock);
    }

    public function relay(int $limit): OutboxRelayResult
    {
        $published = 0;
        $failed    = 0;
        for ($processed = 0; $processed < $limit; $processed++) {
            $result = $this->processNext();
            if ($result === null) {
                break;
            }

            $published += $result ? 1 : 0;
            $failed    += $result ? 0 : 1;
        }

        return new OutboxRelayResult($published, $failed);
    }

    private function processNext(): bool|null
    {
        $publication = $this->publication;
        $message     = $publication->claim();
        if ($message === null) {
            return null;
        }

        $publisher = $this->publisher;
        $result    = $publisher->publish($message);
        if ($result->isSuccessful()) {
            $publication->markPublished($message);

            return true;
        }

        $publication->recordFailure($message);

        return false;
    }
}
