<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\EventManager;

use Backendbase\Domain\ExampleCatalog\Application\DomainEventListener\EntryAddedListener;
use Backendbase\Domain\ExampleCatalog\Application\ExternalIntegrationEventSubscribers\ExampleCatalog\EntryAddedExternalSubscriber;
use Backendbase\Domain\ExampleCatalog\Application\IntegrationEventSubscribers\EntryAddedSubscriber;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\DomainEvents\EntryAdded as EntryAddedDomainEvent;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedCommand;
use Backendbase\Domain\ExampleCatalog\Contracts\ExternalIntegrationEvents\V1\EntryAddedMessage;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\EntryAdded;
use Backendbase\Domain\ExampleCatalog\Contracts\IntegrationEvents\V1\EntryAddedPayload;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Infrastructure\Adapters\EventManager\ContainerAwareSubscriberDispatcher;
use DateTimeImmutable;
use DI\Container;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function array_column;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class EventDiagnosticPrivacyTest extends TestCase
{
    public function testDomainAndBothIntegrationDispatchPathsKeepPayloadsOutOfDiagnosticLogs(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('event-privacy');
        $logger->pushHandler($handler);
        $container = new Container();
        $container->set(LoggerInterface::class, $logger);
        $dispatcher      = new ContainerAwareSubscriberDispatcher($container, $logger);
        $sentinelValue   = 'private-lookup-value-sentinel';
        $sentinelDetails = ['private-contact' => 'private-details-sentinel'];
        $domainEvent     = new EntryAddedDomainEvent(new AddEntry('018f0000-0000-7000-8000-000000000001', EntryType::SYSTEM, null, 'settings', true, 'key', $sentinelValue, $sentinelDetails), new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
        self::assertStringContainsString($sentinelValue, json_encode($domainEvent, JSON_THROW_ON_ERROR));
        new EntryAddedListener($logger)->handle($domainEvent);
        $dispatcher->addEventSubscriber(EntryAdded::EVENT_TYPE, EntryAddedSubscriber::class);
        $integrationEvent = new EntryAdded(new EntryAddedPayload('018f0000-0000-7000-8000-000000000001', 'system', null, 'settings', true, 'key', $sentinelValue, $sentinelDetails), new DateTimeImmutable('2026-10-19T10:00:00.123456Z'));
        self::assertStringContainsString($sentinelValue, json_encode($integrationEvent, JSON_THROW_ON_ERROR));
        $dispatcher->dispatchEvent($integrationEvent);
        $externalName = EntryAddedExternalSubscriber::EVENT_TYPE;
        $dispatcher->addEventSubscriber($externalName, EntryAddedExternalSubscriber::class);
        $dispatcher->dispatchExternalEvent($externalName, new EntryAddedMessage('018f0000-0000-7000-8000-000000000001', new EntryAddedCommand('018f0000-0000-7000-8000-000000000001', 'system', null, 'settings', true, 'key', $sentinelValue, $sentinelDetails)));
        $records = $handler->getRecords();
        $encoded = json_encode($records, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($sentinelValue, $encoded);
        self::assertStringNotContainsString('private-details-sentinel', $encoded);
        self::assertStringNotContainsString('private-contact', $encoded);
        self::assertStringContainsString($externalName, $encoded);
        self::assertStringContainsString('eventVersion', $encoded);
        self::assertContains('EntryAddedListener', array_column($records, 'message'));
        self::assertContains('EntryAddedSubscriber', array_column($records, 'message'));
        self::assertContains('EntryAddedExternalSubscriber', array_column($records, 'message'));
        foreach ($records as $record) {
            self::assertArrayNotHasKey('arguments', $record->context);
            self::assertArrayNotHasKey('message', $record->context);
        }
    }
}
