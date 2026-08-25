<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root\Liveness;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Root\Readiness;
use Backendbase\Shared\Health\DeferredReadinessCheck;
use Backendbase\Shared\Health\ReadinessCheck;
use Backendbase\Shared\Health\ReadinessChecks;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class HealthControllersTest extends TestCase
{
    #[Test]
    public function livenessDoesNotRunDependencyChecks(): void
    {
        $response = (new Liveness())();
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'alive'], $payload);
    }

    #[Test]
    public function readinessReturnsSuccessWhenAllChecksPass(): void
    {
        $mysqlCheck = $this->check('mysql');
        $deferred   = new DeferredReadinessCheck('mysql', static fn (): ReadinessCheck => $mysqlCheck);
        $action     = new Readiness(new ReadinessChecks([$deferred]), $this->createStub(LoggerInterface::class));
        $response   = $this->response($action);
        $payload    = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ready', 'checks' => ['mysql' => 'ready']], $payload);
    }

    #[Test]
    public function readinessReturnsSafeFailureDetails(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('One or more readiness checks failed.');
        $failedCheck = new DeferredReadinessCheck(
            'redis',
            static function (): ReadinessCheck {
                throw new RuntimeException('Sensitive dependency failure.');
            },
        );
        $checks      = new ReadinessChecks([$this->check('mysql'), $failedCheck]);
        $response    = $this->response(new Readiness($checks, $logger));
        $payload     = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('unavailable', $payload['status']);
        self::assertSame(['mysql' => 'ready', 'redis' => 'unavailable'], $payload['checks']);
        self::assertArrayNotHasKey('error', $payload);
    }

    /** @param non-empty-string $name */
    private function check(string $name): ReadinessCheck
    {
        return new readonly class ($name) implements ReadinessCheck {
            /** @param non-empty-string $checkName */
            public function __construct(private string $checkName)
            {
            }

            public function name(): string
            {
                return $this->checkName;
            }

            public function check(): void
            {
            }
        };
    }

    private function response(Readiness $action): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/_status/ready');

        return $action($request, new Response(), []);
    }
}
