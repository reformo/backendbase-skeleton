<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Input;

use Backendbase\Infrastructure\Adapters\Http\Input\RequestObjectInput;
use Backendbase\Infrastructure\Adapters\Http\Middleware\LimitJsonBody;
use Backendbase\Shared\Exception\InvalidUserInput;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function str_repeat;

final class JsonBodyBoundaryTest extends TestCase
{
    public function testBoundedReadingPreservesTheParserStreamAndRejectsNumericFields(): void
    {
        $body = new Stream('php://temp', 'r+');
        $body->write('{"name":"Ada"}');
        $request = new ServerRequest()->withBody($body);
        self::assertSame(['name' => 'Ada'], RequestObjectInput::fromRequest($request, ['name']));
        self::assertSame('{"name":"Ada"}', $body->getContents());
        $this->expectException(InvalidUserInput::class);
        RequestObjectInput::read([0 => 'value'], ['0']);
    }

    #[DataProvider('invalidBodies')]
    public function testNonObjectMalformedAndOversizedBodiesFailExplicitly(string $encoded): void
    {
        $body = new Stream('php://temp', 'r+');
        $body->write($encoded);
        $request = new ServerRequest()->withBody($body);
        $this->expectException(InvalidUserInput::class);
        RequestObjectInput::fromRequest($request, []);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidBodies(): iterable
    {
        yield 'list' => ['[]'];
        yield 'scalar' => ['"text"'];
        yield 'malformed' => ['{'];
        yield 'raw whitespace excess' => [str_repeat(' ', 65536) . '{}'];
    }

    public function testNonSeekableBodiesAndUnexpectedLifecycleBodiesAreRejected(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        try {
            RequestObjectInput::boundedBody(new ServerRequest()->withBody($stream));
            self::fail('Unbounded nonseekable input must be rejected.');
        } catch (InvalidUserInput $exception) {
            self::assertSame('The request body must support bounded JSON reading.', $exception->getMessage());
        }

        $body = new Stream('php://temp', 'r+');
        $body->write('{}');
        $body->rewind();
        $this->expectException(InvalidUserInput::class);
        RequestObjectInput::assertEmptyBody(new ServerRequest()->withBody($body));
    }

    public function testBodyLimitRunsBeforeTheBodyParser(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new Response());
        $middleware = new LimitJsonBody();
        $middleware->process(new ServerRequest(), $handler);
        $body = new Stream('php://temp', 'r+');
        $body->write(str_repeat(' ', 65537));
        $request = new ServerRequest()->withBody($body)->withHeader('Content-Type', 'application/json');
        $this->expectException(InvalidUserInput::class);
        $middleware->process($request, $handler);
    }
}
