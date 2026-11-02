<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi\Sessions;

use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Laminas\Diactoros\ServerRequestFactory;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Factory\AppFactory;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class SessionDiagnosticPrivacyTest extends TestCase
{
    public function testRefreshFailureDoesNotLogCredentialsOrExceptionMessages(): void
    {
        $accessToken  = 'synthetic-access-token-for-privacy-test';
        $refreshToken = 'synthetic-refresh-token-for-privacy-test';
        $request      = (new ServerRequestFactory())->createServerRequest('POST', '/auth/refresh');
        $request      = $request->withHeader('Authorization', 'Bearer ' . $accessToken);
        $request      = $request->withHeader('Cookie', 'refresh=' . $refreshToken);
        $exception    = new RuntimeException('Refresh failure: ' . $refreshToken, 0, new RuntimeException($accessToken));
        $logHandler   = new TestHandler();
        $logger       = new Logger('session-privacy-test');
        $logger->pushHandler($logHandler);
        $app      = AppFactory::create();
        $handler  = new HttpErrorHandler($app->getCallableResolver(), $app->getResponseFactory(), $logger, new DomainErrorProblemDetailsMapper());
        $response = $handler($request, $exception, false, true, true);

        self::assertSame(500, $response->getStatusCode());
        $records = $logHandler->getRecords();
        self::assertCount(1, $records);
        $log = json_encode($records, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('HTTP request failed.', $log);
        self::assertStringNotContainsString($accessToken, $log);
        self::assertStringNotContainsString($refreshToken, $log);
        self::assertStringNotContainsString($accessToken, (string) $response->getBody());
        self::assertStringNotContainsString($refreshToken, (string) $response->getBody());
    }
}
