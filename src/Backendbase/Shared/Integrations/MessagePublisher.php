<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations;

use Backendbase\Shared\Integrations\Messaging\Message;
use Backendbase\Shared\Integrations\Operation\MessagePublicationResult;

interface MessagePublisher
{
    public function publish(Message $message): MessagePublicationResult;
}
