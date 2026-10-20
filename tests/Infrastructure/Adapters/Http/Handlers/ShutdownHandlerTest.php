<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Handlers;

use Backendbase\Infrastructure\Adapters\Http\Handlers\ShutdownHandler;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Backendbase\Shared\Services\Settings;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Interfaces\ErrorHandlerInterface;

use function error_clear_last;
use function error_reporting;
use function file_get_contents;
use function ob_get_clean;
use function ob_start;
use function trigger_error;

use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;

final class ShutdownHandlerTest extends TestCase
{
    #[Test]
    public function itIgnoresTheAbsenceOfAShutdownError(): void
    {
        error_clear_last();
        $errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $errorHandler->expects(self::never())->method('__invoke');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $this->shutdownHandler($errorHandler, false, $logger)();
    }

    /** @param callable(): void $setError */
    #[DataProvider('nonFatalErrors')]
    #[Test]
    public function itPreservesTheResponseAfterANonFatalError(callable $setError): void
    {
        $errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $errorHandler->expects(self::never())->method('__invoke');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $setError();
        ob_start();
        try {
            echo '{"result":"unchanged"}';
            $this->shutdownHandler($errorHandler, true, $logger)();
            $output = ob_get_clean();
        } finally {
            error_clear_last();
        }

        self::assertSame('{"result":"unchanged"}', $output);
    }

    #[DataProvider('fatalErrors')]
    #[Test]
    public function itReportsFatalErrors(int $type, bool $displayDetails): void
    {
        $message  = $displayDetails
            ? 'FATAL ERROR: shutdown failure.  on line 42 in file /app/index.php.'
            : 'An error while processing your request. Please try again later.';
        $response = new Response();
        $response->getBody()->write('shutdown-response');
        $errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $errorHandler->expects(self::once())->method('__invoke')->with(
            self::anything(),
            self::callback(static fn (HttpInternalServerErrorException $exception): bool => $exception->getMessage() === $message),
            $displayDetails,
            false,
            false,
        )->willReturn($response);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'PHP shutdown error: shutdown failure',
            ['exception' => 'PHPShutdownError', 'file' => '/app/index.php', 'line' => 42, 'type' => $type],
        );
        $handler = $this->shutdownHandler($errorHandler, $displayDetails, $logger);
        // Dispatch the PHP error record directly because real fatal errors terminate PHPUnit.
        $method = new ReflectionMethod(ShutdownHandler::class, 'handleError');
        ob_start();
        $method->invoke($handler, ['type' => $type, 'message' => 'shutdown failure', 'file' => '/app/index.php', 'line' => 42]);
        $output = ob_get_clean();
        self::assertSame('shutdown-response', $output);
    }

    /** @return iterable<string, array{callable(): void}> */
    public static function nonFatalErrors(): iterable
    {
        yield 'deprecation' => [static fn () => self::setUserError(E_USER_DEPRECATED)];
        yield 'user warning' => [static fn () => self::setUserError(E_USER_WARNING)];
        yield 'user notice' => [static fn () => self::setUserError(E_USER_NOTICE)];
        yield 'native warning' => [
            static function (): void {
                $level = error_reporting(0);
                file_get_contents('/missing-backendbase-shutdown-file');
                error_reporting($level);
            },
        ];
    }

    /** @return iterable<string, array{int, bool}> */
    public static function fatalErrors(): iterable
    {
        yield 'error' => [E_ERROR, true];
        yield 'parse' => [E_PARSE, true];
        yield 'core' => [E_CORE_ERROR, true];
        yield 'compile' => [E_COMPILE_ERROR, true];
        yield 'user' => [E_USER_ERROR, true];
        yield 'recoverable' => [E_RECOVERABLE_ERROR, true];
        yield 'hidden details' => [E_ERROR, false];
    }

    private function shutdownHandler(ErrorHandlerInterface $errorHandler, bool $displayDetails, LoggerInterface $logger): ShutdownHandler
    {
        return new ShutdownHandler(
            (new ServerRequestFactory())->createServerRequest('GET', '/resource'),
            $errorHandler,
            new HttpHeaderSettings(new Settings([
                'headers' => ['Access-Control-Allow-Origin' => 'https://app.example.com', 'Access-Control-Allow-Headers' => 'Content-Type'],
            ])),
            $displayDetails,
            $logger,
        );
    }

    private static function setUserError(int $type): void
    {
        $level = error_reporting(0);
        trigger_error('shutdown failure', $type);
        error_reporting($level);
    }
}
