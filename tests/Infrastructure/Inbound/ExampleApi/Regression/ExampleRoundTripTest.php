<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Regression;

use Backendbase\Domain\ExampleCatalog\Application\EntryWrites;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Doctrine\DoctrineEntryRepositoryTestCase;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\ChangeExampleDetails;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\NewExample;
use Backendbase\Shared\CQRS\CommandBus;
use DateTimeImmutable;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\Support\Time\FrozenClock;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class ExampleRoundTripTest extends DoctrineEntryRepositoryTestCase
{
    #[Test]
    public function itReadsCreatedAndChangedJsonWithoutLosingValues(): void
    {
        $writes   = new EntryWrites($this->writeRepository(), new FrozenClock(new DateTimeImmutable('2026-10-16T10:00:00Z')));
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::exactly(2))->method('handle')->willReturnCallback(
            static fn (AddEntry|ChangeEntry $command) => $command instanceof AddEntry ? $writes->add($command) : $writes->change($command),
        );
        $details = ['heroImage' => (object) ['path' => 'A&B.png'], 'email' => 'alice (tag)@example.com', 'password' => 'catalog text', 'emptyObject' => (object) [], 'emptyList' => []];
        $this->write('POST', ['lookupKey' => 'round-trip', 'lookupValue' => 'A & B', 'details' => $details], $commands);
        $this->assertStored('A & B', $details);
        $this->write('PATCH', ['lookupValue' => 'C < D &amp;', 'details' => $details], $commands);
        $this->assertStored('C < D &amp;', $details);
        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM example_table'));
    }

    /** @param array<string, mixed> $payload */
    private function write(string $method, array $payload, CommandBus $commands): void
    {
        $factory  = new StreamFactory();
        $body     = $factory->createStream(json_encode($payload, JSON_THROW_ON_ERROR));
        $request  = new ServerRequestFactory()->createServerRequest($method, '/examples');
        $request  = $request->withBody($body);
        $request  = $request->withAttribute('type-slug', 'system');
        $request  = $request->withAttribute('example-group', 'settings');
        $request  = $request->withAttribute('example-key', 'round-trip');
        $action   = $method === 'POST' ? new NewExample($commands, new NullLogger()) : new ChangeExampleDetails($commands, new NullLogger());
        $response = $action($request, new Response(), []);
        self::assertSame(204, $response->getStatusCode());
    }

    /** @param array<string, mixed> $details */
    private function assertStored(string $value, array $details): void
    {
        $reader = $this->readRepository();
        $entry  = $reader->getEntryByCriteria(new GetEntryByCriteria(EntryType::SYSTEM, null, 'settings', 'round-trip'));
        self::assertNotNull($entry);
        self::assertSame($value, $entry->lookupValue());
        self::assertSame(json_encode($details, JSON_THROW_ON_ERROR), json_encode($entry->details(), JSON_THROW_ON_ERROR));
    }
}
