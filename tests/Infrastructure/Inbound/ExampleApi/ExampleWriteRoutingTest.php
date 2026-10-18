<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandBus;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\Http\JsonRequestBody;

final class ExampleWriteRoutingTest extends TestCase
{
    private const string COLLECTION_PATH = '/example-types/system/groups/settings/examples';

    #[Test]
    public function itAddsAnExampleWithAUserTokenAndApiKey(): void
    {
        $command  = null;
        $response = $this->write('POST', self::COLLECTION_PATH, $command);

        self::assertInstanceOf(AddEntry::class, $command);
        self::assertSame(EntryType::SYSTEM, $command->type());
        self::assertNull($command->typeTargetId());
        self::assertSame('settings', $command->group());
        self::assertSame('page-size', $command->key());
        self::assertSame('25 & items', $command->value());
        self::assertSame(['unit' => 'items'], $command->details());
        self::assertFalse($command->isActive());
        self::assertSame($command->entryId(), $response->getHeaderLine('Backendbase-Insert-Id'));
        self::assertNotSame('', $response->getHeaderLine('Backendbase-Insert-Id'));
    }

    #[Test]
    public function itChangesAnExampleWithAUserTokenAndApiKey(): void
    {
        $command = null;
        $this->write('PATCH', self::COLLECTION_PATH . '/page-size', $command);

        self::assertInstanceOf(ChangeEntry::class, $command);
        $identity = $command->identity();
        self::assertSame('page-size', $identity->key());
        self::assertSame('25 & items', $command->value());
        self::assertSame(['unit' => 'items'], $command->details());
        self::assertFalse($command->isActive());
    }

    #[Test]
    public function itRemovesAnExampleWithAUserTokenAndApiKey(): void
    {
        $command = null;
        $this->write('DELETE', self::COLLECTION_PATH . '/page-size', $command);

        self::assertInstanceOf(RemoveEntry::class, $command);
        $identity = $command->identity();
        self::assertSame(EntryType::SYSTEM, $identity->type());
        self::assertSame('settings', $identity->group());
        self::assertSame('page-size', $identity->key());
    }

    #[Test]
    #[DataProvider('invalidApiKeys')]
    public function itRejectsWritesBeforeDispatch(string $method, string $apiKey): void
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::never())->method('handle');
        $app     = ExampleApiFixture::build($bus);
        $path    = self::COLLECTION_PATH . ($method === 'POST' ? '' : '/page-size');
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withHeader('Backendbase-Api-Key', $apiKey)
            ->withBody(JsonRequestBody::create(['lookupValue' => '25']));

        self::assertSame(401, $app->handle($request)->getStatusCode());
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidApiKeys(): iterable
    {
        yield 'post missing' => ['POST', ''];
        yield 'post invalid' => ['POST', 'invalid'];
        yield 'patch missing' => ['PATCH', ''];
        yield 'patch invalid' => ['PATCH', 'invalid'];
        yield 'delete missing' => ['DELETE', ''];
        yield 'delete invalid' => ['DELETE', 'invalid'];
    }

    private function write(string $method, string $path, Command|null &$command): ResponseInterface
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::once())->method('handle')
            ->willReturnCallback(static function (Command $dispatched) use (&$command): void {
                $command = $dispatched;
            });
        [$app, $token] = ExampleApiFixture::authenticated($bus);
        $payload       = [
            'lookupValue' => '25 & items',
            'isActive' => false,
            'details' => ['unit' => 'items'],
        ];
        if ($method === 'POST') {
            $payload['lookupKey'] = 'page-size';
        }

        $request  = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withHeader('Backendbase-Api-Key', 'example-api-key')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Idempotency-Key', 'write-example')
            ->withBody(JsonRequestBody::create($payload));
        $response = $app->handle($request);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());

        return $response;
    }
}
