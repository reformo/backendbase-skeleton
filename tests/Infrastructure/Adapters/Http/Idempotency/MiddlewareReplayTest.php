<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Idempotency;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Http\ReplayMiddlewareFixture;

use function str_repeat;

final class MiddlewareReplayTest extends TestCase
{
    #[DataProvider('methods')]
    public function testRetriesReuseTheResultWhileTraceIdentifiersCanChange(string $method): void
    {
        $calls         = 0;
        $app           = ReplayMiddlewareFixture::app(static function () use (&$calls): EmptyResponse {
            $calls++;

            return new EmptyResponse(204, ['Backendbase-Insert-Id' => 'created-identity']);
        });
        $request       = ReplayMiddlewareFixture::request($method);
        $firstRequest  = $request->withHeader('X-Request-Id', 'first-trace');
        $secondRequest = $request->withHeader('X-Request-Id', 'second-trace');
        $first         = $app->handle($firstRequest);
        $second        = $app->handle($secondRequest);
        self::assertSame(204, $first->getStatusCode());
        self::assertSame(204, $second->getStatusCode());
        self::assertSame('created-identity', $second->getHeaderLine('Backendbase-Insert-Id'));
        self::assertSame('', (string) $second->getBody());
        self::assertSame(1, $calls);
    }

    #[DataProvider('changedInputs')]
    public function testChangedContentCannotExecuteUnderTheSameKey(string $change): void
    {
        $calls   = 0;
        $app     = ReplayMiddlewareFixture::app(static function () use (&$calls): EmptyResponse {
            $calls++;

            return new EmptyResponse();
        });
        $request = ReplayMiddlewareFixture::request();
        $first   = $app->handle($request);
        self::assertSame(204, $first->getStatusCode());
        $changed = match ($change) {
            'body' => ReplayMiddlewareFixture::request(body: '{"changed":true}'),
            'path' => $request->withUri(new Uri('/write/two')),
            'query' => $request->withUri(new Uri('/write/one?target=two')),
            'method' => $request->withMethod('PUT'),
            default => $request->withHeader($change, 'changed'),
        };
        $retry = $app->handle($changed);
        self::assertSame(422, $retry->getStatusCode());
        self::assertSame(1, $calls);
    }

    public function testDifferentActorsCanUseTheSameKeyIndependently(): void
    {
        $calls   = 0;
        $app     = ReplayMiddlewareFixture::app(static function () use (&$calls): EmptyResponse {
            $calls++;

            return new EmptyResponse();
        });
        $request = ReplayMiddlewareFixture::request();
        foreach (['1', '2'] as $suffix) {
            $id        = '019f6322-1234-7000-8000-00000000000' . $suffix;
            $principal = new Principal(Realm::USER, ['accountId' => $id, 'actorId' => $id, 'sid' => $id, 'jti' => $id, 'authVersion' => 1]);
            $scoped    = $request->withAttribute('identityPrincipal', $principal);
            $response  = $app->handle($scoped);
            self::assertSame(204, $response->getStatusCode());
        }

        self::assertSame(2, $calls);
    }

    /** @param string|list<string> $key */
    #[DataProvider('invalidKeys')]
    public function testInvalidKeysNeverInvokeTheOperation(string|array $key): void
    {
        $app      = ReplayMiddlewareFixture::app(static function (): never {
            self::fail('Invalid keys cannot execute.');
        });
        $request  = ReplayMiddlewareFixture::request();
        $invalid  = $request->withHeader('Idempotency-Key', $key);
        $response = $app->handle($invalid);
        self::assertSame(400, $response->getStatusCode());
    }

    /** @return iterable<string, array{string}> */
    public static function methods(): iterable
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            yield $method => [$method];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function changedInputs(): iterable
    {
        foreach (['body', 'path', 'query', 'method', 'Authorization', 'Backendbase-Api-Key', 'Accept-Language', 'The-Timezone-IANA'] as $change) {
            yield $change => [$change];
        }
    }

    /** @return iterable<string, array{string|list<string>}> */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'multiple' => [['one', 'two']];
        yield 'comma' => ['one,two'];
        yield 'space' => ['one two'];
        yield 'oversized' => [str_repeat('x', 256)];
        yield 'unicode' => ['ışık'];
        yield 'punctuation prefix' => ['-key'];
    }
}
