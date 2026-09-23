<?php

declare(strict_types=1);

namespace Backendbase\Shared;

interface ServiceProvider
{
    /** @return iterable<class-string, class-string> */
    public static function getDefinitions(): iterable;

    /** @return array<class-string, class-string> */
    public static function getHandlers(): array;

    /**
     * @return iterable<int, array{
     *     events: array<int, string>,
     *     subscriberFQCN: class-string,
     *     messageFQCN?: class-string,
     *     eventVersion?: string
     * }>
     */
    public static function getIntegrationEventSubscribers(): iterable;
}
