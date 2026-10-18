<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Idempotency;

use Backendbase\Shared\Idempotency\RequestOutstanding;
use Backendbase\Shared\Idempotency\RequestReplayStore;
use Laminas\Diactoros\Response\EmptyResponse;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Http\ReplayMiddlewareFixture;

final class ReplayFailureTest extends TestCase
{
    public function testAConcurrentAttemptCannotEnterTheHandler(): void
    {
        $calls   = 0;
        $app     = null;
        $app     = ReplayMiddlewareFixture::app(static function () use (&$app, &$calls): EmptyResponse {
            self::assertNotNull($app);
            $calls++;
            $concurrent = $app->handle(ReplayMiddlewareFixture::request());
            self::assertSame(409, $concurrent->getStatusCode());

            return new EmptyResponse();
        });
        $first   = $app->handle(ReplayMiddlewareFixture::request());
        $request = ReplayMiddlewareFixture::request()->withHeader('X-Request-Id', 'retry');
        $retry   = $app->handle($request);
        self::assertSame(204, $first->getStatusCode());
        self::assertSame(204, $retry->getStatusCode());
        self::assertSame(1, $calls);
    }

    public function testFailureAfterAnEffectIsRecordedWithoutRepeatingTheEffect(): void
    {
        $effects = 0;
        $app     = ReplayMiddlewareFixture::app(static function () use (&$effects): never {
            $effects++;

            throw new RuntimeException('Sensitive internal failure.');
        });
        $first   = $app->handle(ReplayMiddlewareFixture::request());
        $retry   = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(500, $first->getStatusCode());
        $original = (string) $first->getBody();
        self::assertSame($original, (string) $retry->getBody());
        self::assertStringNotContainsString('Sensitive', (string) $first->getBody());
        self::assertSame(1, $effects);
    }

    public function testCompletionFailureLeavesTheRequestBlocked(): void
    {
        $store    = $this->createMock(RequestReplayStore::class);
        $attempts = 0;
        $claim    = $store->method('claim');
        $claim->willReturnCallback(static function () use (&$attempts): string|null {
            $attempts++;
            if ($attempts > 1) {
                throw new RequestOutstanding();
            }

            return null;
        });
        $completion = $store->expects(self::once());
        $completion->method('complete');
        $completion->willThrowException(new RuntimeException('storage credentials must stay private'));
        $effects = 0;
        $app     = ReplayMiddlewareFixture::app(static function () use (&$effects): EmptyResponse {
            $effects++;

            return new EmptyResponse();
        }, $store);
        $first   = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(503, $first->getStatusCode());
        self::assertStringNotContainsString('credentials', (string) $first->getBody());
        $retry = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(409, $retry->getStatusCode());
        self::assertSame(1, $effects);
    }

    public function testUnavailableStoragePreventsExecution(): void
    {
        $store = $this->createMock(RequestReplayStore::class);
        $claim = $store->method('claim');
        $claim->willThrowException(new RuntimeException('Connection failed.'));
        $completion = $store->expects(self::never());
        $completion->method('complete');
        $app      = ReplayMiddlewareFixture::app(static function (): never {
            self::fail('Unclaimed requests cannot execute.');
        }, $store);
        $response = $app->handle(ReplayMiddlewareFixture::request());
        self::assertSame(503, $response->getStatusCode());
    }

    public function testReadsAndPreflightDoNotUseReplayStorage(): void
    {
        $store = $this->createMock(RequestReplayStore::class);
        $claim = $store->expects(self::never());
        $claim->method('claim');
        $app = ReplayMiddlewareFixture::app(static fn (): EmptyResponse => new EmptyResponse(), $store);
        foreach (['GET', 'OPTIONS'] as $method) {
            $request  = ReplayMiddlewareFixture::request($method);
            $read     = $request->withoutHeader('Idempotency-Key');
            $response = $app->handle($read);
            self::assertSame(204, $response->getStatusCode());
        }
    }
}
