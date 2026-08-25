<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Actions;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

use function json_decode;
use function restore_error_handler;
use function set_error_handler;
use function stream_wrapper_register;
use function stream_wrapper_restore;
use function stream_wrapper_unregister;

use const JSON_THROW_ON_ERROR;

final class ActionTest extends TestCase
{
    #[Test]
    public function itRespondsWithJsonDataAndResolvesArguments(): void
    {
        $action   = new TestAction($this->createStub(LoggerInterface::class));
        $response = $action(
            (new ServerRequestFactory())->createServerRequest('GET', '/resource'),
            new Response(),
            ['resourceId' => 'resource-id'],
        );
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('resource-id', $payload['data']['resourceId']);
    }

    #[Test]
    public function itRejectsAnUnresolvedArgument(): void
    {
        $action                  = new TestAction($this->createStub(LoggerInterface::class));
        $action->missingArgument = true;

        $this->expectException(HttpBadRequestException::class);
        $action(
            (new ServerRequestFactory())->createServerRequest('GET', '/resource'),
            new Response(),
            [],
        );
    }

    #[Test]
    public function itReadsObjectAndArrayJsonInput(): void
    {
        $action  = new TestAction($this->createStub(LoggerInterface::class));
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/resource');

        $object = $this->withPhpInput('{"name":"value"}', static fn () => $action->formData($request));
        $array  = $this->withPhpInput('["value"]', static fn () => $action->formData($request));

        self::assertIsObject($object);
        self::assertSame('value', ((array) $object)['name']);
        self::assertSame(['value'], $array);
    }

    #[Test]
    public function itRejectsUnreadableMalformedAndScalarJsonInput(): void
    {
        $action  = new TestAction($this->createStub(LoggerInterface::class));
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/resource');

        foreach ([false, '{', '1'] as $input) {
            try {
                $this->withPhpInput($input, static fn () => $action->formData($request));
                self::fail('Invalid JSON input must fail.');
            } catch (HttpBadRequestException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function withPhpInput(string|false $input, callable $callback): mixed
    {
        PhpInputStream::$body = $input;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', PhpInputStream::class);
        set_error_handler(static fn (): bool => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
            stream_wrapper_restore('php');
        }
    }
}
