<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Middleware;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Infrastructure\Adapters\Diagnostics\SafeExceptionContext;
use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\Middleware\MeasureRequest;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

use function array_keys;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class MeasureRequestTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testMeasurementsUseStableRouteLabelsAndExcludeSensitiveInput(int $status, string|null $failure): void
    {
        $request = new ServerRequest(uri: '/identity/private-id?password=secret', method: 'POST');
        $request = $request->withHeader('Authorization', 'Bearer secret');
        $logger  = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('http.request.completed', self::callback(static function (array $context) use ($status): bool {
            self::assertSame(['route', 'method', 'status', 'durationMilliseconds'], array_keys($context));
            self::assertSame('unmatched', $context['route']);
            self::assertSame('POST', $context['method']);
            self::assertSame($status, $context['status']);
            self::assertGreaterThanOrEqual(0, $context['durationMilliseconds']);

            return true;
        }));
        $handler = $this->createMock(RequestHandlerInterface::class);
        $call    = $handler->expects(self::once())->method('handle');
        if ($failure === null) {
            $call->willReturn(new Response(status: $status));
        }

        if ($failure !== null) {
            $exception = match ($failure) {
                'http' => new HttpNotFoundException($request),
                'domain' => AuthenticationDenied::create('Authentication is not permitted.'),
                default => new RuntimeException('secret provider detail'),
            };
            $call->willThrowException($exception);
            $this->expectException($exception::class);
        }

        $response = new MeasureRequest($logger, new DomainErrorProblemDetailsMapper())->process($request, $handler);
        self::assertSame($status, $response->getStatusCode());
    }

    /** @return iterable<string, array{int, string|null}> */
    public static function outcomes(): iterable
    {
        yield 'success' => [201, null];
        yield 'HTTP failure' => [404, 'http'];
        yield 'domain failure' => [401, 'domain'];
        yield 'unexpected failure' => [500, 'unexpected'];
    }

    public function testExceptionContextExcludesMessagesAndCallArguments(): void
    {
        try {
            self::throwSensitiveFailure('password-and-token');
        } catch (Throwable $exception) {
            $context = SafeExceptionContext::from($exception);
            self::assertSame(RuntimeException::class, $context['exception']);
            self::assertStringNotContainsString('password-and-token', json_encode($context, JSON_THROW_ON_ERROR));
            foreach (SafeExceptionContext::frames($exception) as $frame) {
                self::assertArrayNotHasKey('args', $frame);
            }
        }
    }

    private static function throwSensitiveFailure(string $secret): never
    {
        throw new RuntimeException($secret);
    }
}
