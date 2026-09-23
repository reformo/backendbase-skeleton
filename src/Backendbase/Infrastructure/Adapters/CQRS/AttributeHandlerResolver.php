<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\CQRS;

use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\HandlerResolver;
use Backendbase\Shared\CQRS\Query;
use Override;
use ReflectionClass;
use UnexpectedValueException;

use function count;

final readonly class AttributeHandlerResolver implements HandlerResolver
{
    /** @param Command|Query<mixed> $message */
    #[Override]
    public function handlerFor(Command|Query $message): string
    {
        $messageClass = $message::class;
        $attributes   = new ReflectionClass($messageClass)->getAttributes(CQRSHandler::class);
        if (count($attributes) !== 1) {
            throw new UnexpectedValueException('Expected one CQRSHandler attribute on ' . $messageClass);
        }

        return $attributes[0]->newInstance()->handlerName;
    }
}
