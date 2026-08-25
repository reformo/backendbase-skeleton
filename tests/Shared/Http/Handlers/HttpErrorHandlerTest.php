<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Handlers;

use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Http\Handlers\HttpErrorHandler;
use Backendbase\Shared\Services\Translator;
use Laminas\Diactoros\ServerRequestFactory;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Factory\AppFactory;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class HttpErrorHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsSafeInternalServerErrorForUnexpectedActionFailure(): void
    {
        $logHandler = new TestHandler();
        $logger     = new Logger('http-error-handler-test');
        $logger->pushHandler($logHandler);

        $action = new class ($logger) extends Action {
            #[Override]
            protected function action(): Response
            {
                throw new RuntimeException('Sensitive implementation failure.');
            }
        };

        $app = AppFactory::create();
        $app->get('/failure', $action);
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorMiddleware->setDefaultErrorHandler(new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            $logger,
        ));

        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/failure');
        $response = $app->handle($request);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame('server/internal-error', $payload['code']);
        self::assertSame('An internal error has occurred while processing your request.', $payload['detail']);
        self::assertArrayNotHasKey('exceptionDetails', $payload);
        self::assertTrue($logHandler->hasErrorThatContains('Sensitive implementation failure.'));
    }

    #[Test]
    public function itMapsSlimHttpExceptionsToStableProblemCodes(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/resource');
        $cases   = [
            [new HttpNotFoundException($request), 'http/resource-not-found'],
            [new HttpMethodNotAllowedException($request), 'http/not-allowed'],
            [new HttpUnauthorizedException($request), 'http/unauthenticated'],
            [new HttpForbiddenException($request), 'http/insufficient-privileges'],
            [new HttpBadRequestException($request), 'http/bad-request'],
            [new HttpNotImplementedException($request), 'http/not-implemented'],
        ];

        foreach ($cases as [$exception, $expectedCode]) {
            $response = $this->handler()->__invoke($request, $exception, false, false, false);
            $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($exception->getCode(), $response->getStatusCode());
            self::assertSame($expectedCode, $payload['code']);
        }
    }

    #[Test]
    public function itMapsAndTranslatesProblemDetails(): void
    {
        $request    = (new ServerRequestFactory())->createServerRequest('GET', '/resource');
        $translator = new Translator('en', ['en' => ['resource' => ['missing' => 'Resource missing.']]]);
        $exception  = ResourceNotFound::create('Missing.', ['message' => 'resource.missing', 'status' => 409]);

        $response = $this->handler($translator)->__invoke($request, $exception, false, false, false);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('general/resource-not-found', $payload['code']);
        self::assertSame('Resource missing.', $payload['message']);
    }

    #[Test]
    public function itIncludesUnexpectedExceptionDetailsWhenEnabled(): void
    {
        $request   = (new ServerRequestFactory())->createServerRequest('GET', '/resource');
        $exception = new RuntimeException('Detailed failure.');

        $response = $this->handler()->__invoke($request, $exception, true, false, false);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('RuntimeException', $payload['exceptionDetails']['exception']);
        self::assertSame('Detailed failure.', $payload['exceptionDetails']['message']);
    }

    private function handler(Translator|null $translator = null): HttpErrorHandler
    {
        $app = AppFactory::create();

        return new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new Logger('http-error-handler-test'),
            $translator,
        );
    }
}
