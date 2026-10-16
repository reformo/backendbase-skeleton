<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Regression;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\AddEntry;
use Backendbase\Domain\ExampleCatalog\Contracts\Command\ChangeEntry;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\ChangeExampleDetails;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\NewExample;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Exception\InvalidUserInput;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

final class ExampleBoundaryRegressionTest extends TestCase
{
    #[Test]
    public function itPreservesTextAndArbitraryDetailNamesDuringCreationAndChange(): void
    {
        $payload = [
            'lookupValue' => 'A & B <tag> "quoted" &amp;',
            'details' => ['email' => 'alice (tag)@example.com', 'password' => 'plain catalog text', 'label' => 'A & B'],
        ];
        $created = $this->dispatch('POST', $payload);
        $changed = $this->dispatch('PATCH', $payload);
        self::assertSame($payload['lookupValue'], $created->value());
        self::assertSame($payload['lookupValue'], $changed->value());
        self::assertSame($payload['details'], $created->details());
        self::assertSame($payload['details'], $changed->details());
    }

    #[Test]
    #[DataProvider('invalidBodies')]
    public function itRejectsInvalidBodiesBeforeDispatch(string $method, string $body, string $group): void
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::never())->method('handle');
        $this->expectException(InvalidUserInput::class);
        $this->invoke($method, $body, $group, $bus);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidBodies(): iterable
    {
        foreach (['POST', 'PATCH'] as $method) {
            yield $method . ' nonempty list' => [$method, '{"lookupValue":"ok","details":["x"]}', 'settings'];
            yield $method . ' empty list' => [$method, '{"lookupValue":"ok","details":[]}', 'settings'];
            yield $method . ' oversized value' => [$method, json_encode(['lookupValue' => str_repeat('ü', 2049)], JSON_THROW_ON_ERROR), 'settings'];
            yield $method . ' oversized group' => [$method, '{"lookupValue":"ok"}', str_repeat('g', 33)];
            yield $method . ' numeric detail name' => [$method, '{"lookupValue":"ok","details":{"0":"x"}}', 'settings'];
        }

        yield 'oversized key' => ['POST', json_encode(['lookupValue' => 'ok', 'lookupKey' => str_repeat('k', 161)], JSON_THROW_ON_ERROR), 'settings'];
        yield 'empty key' => ['POST', '{"lookupValue":"ok","lookupKey":""}', 'settings'];
        yield 'array body' => ['PATCH', '[]', 'settings'];
        yield 'malformed JSON' => ['POST', '{', 'settings'];
        yield 'null body' => ['POST', 'null', 'settings'];
    }

    #[Test]
    public function itAcceptsMaximumCharacterLengthsWithoutEscaping(): void
    {
        $payload = ['lookupKey' => str_repeat('ü', 160), 'lookupValue' => str_repeat('&', 2048)];
        $command = $this->dispatch('POST', $payload, str_repeat('g', 32));
        self::assertInstanceOf(AddEntry::class, $command);
        self::assertSame($payload['lookupKey'], $command->key());
        self::assertSame($payload['lookupValue'], $command->value());
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(string $method, array $payload, string $group = 'settings'): AddEntry|ChangeEntry
    {
        $command = null;
        $bus     = $this->createMock(CommandBus::class);
        $bus->expects(self::once())->method('handle')->willReturnCallback(
            static function (Command $dispatched) use (&$command): void {
                $command = $dispatched;
            },
        );
        $this->invoke($method, json_encode($payload, JSON_THROW_ON_ERROR), $group, $bus);
        self::assertTrue($command instanceof AddEntry || $command instanceof ChangeEntry);

        return $command;
    }

    private function invoke(string $method, string $body, string $group, CommandBus $bus): void
    {
        $stream  = new StreamFactory()->createStream($body);
        $request = new ServerRequestFactory()->createServerRequest($method, '/examples');
        $request = $request->withBody($stream);
        $request = $request->withAttribute('type-slug', 'system');
        $request = $request->withAttribute('example-group', $group);
        $request = $request->withAttribute('example-key', 'key');
        $action  = $method === 'POST' ? new NewExample($bus, new NullLogger()) : new ChangeExampleDetails($bus, new NullLogger());
        $action($request, new Response(), []);
    }
}
