<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Handlers;

use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Backendbase\Shared\Http\Handlers\ShutdownHandler;
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

use const E_USER_DEPRECATED;
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

        $this->shutdownHandler($errorHandler, false)();

        self::addToAssertionCount(1);
    }

    #[Test]
    public function itIgnoresDeprecationErrors(): void
    {
        $errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $errorHandler->expects(self::never())->method('__invoke');
        $this->setUserError(E_USER_DEPRECATED);

        try {
            $this->shutdownHandler($errorHandler, false)();
            self::addToAssertionCount(1);
        } finally {
            error_clear_last();
        }
    }

    #[Test]
    public function itFormatsFatalErrorDetails(): void
    {
        $method = new ReflectionMethod(ShutdownHandler::class, 'fatalErrorMessage');

        self::assertSame(
            'FATAL ERROR: shutdown failure.  on line 42 in file /app/index.php.',
            $method->invoke(null, 'shutdown failure', 42, '/app/index.php'),
        );
    }

    /** @param callable(): void $setError */
    #[DataProvider('displayedErrors')]
    #[Test]
    public function itReportsShutdownErrorsWithDetails(callable $setError, string $expectedMessage): void
    {
        $response = new Response();
        $response->getBody()->write('shutdown-response');
        $errorHandler = $this->createMock(ErrorHandlerInterface::class);
        $errorHandler->expects(self::once())
            ->method('__invoke')
            ->with(
                self::anything(),
                self::callback(static function (HttpInternalServerErrorException $exception) use ($expectedMessage): bool {
                    self::assertStringContainsString($expectedMessage, $exception->getMessage());

                    return true;
                }),
                true,
                false,
                false,
            )
            ->willReturn($response);
        $setError();

        ob_start();
        try {
            $this->shutdownHandler($errorHandler, true)();
            $output = ob_get_clean();
        } finally {
            error_clear_last();
        }

        self::assertSame('shutdown-response', $output);
    }

    /** @return iterable<string, array{callable(): void, string}> */
    public static function displayedErrors(): iterable
    {
        yield 'warning' => [static fn () => self::setUserError(E_USER_WARNING), 'WARNING: shutdown failure'];
        yield 'notice' => [static fn () => self::setUserError(E_USER_NOTICE), 'NOTICE: shutdown failure'];
        yield 'default' => [
            static function (): void {
                $level = error_reporting(0);
                file_get_contents('/missing-backendbase-shutdown-file');
                error_reporting($level);
            },
            'ERROR: file_get_contents',
        ];
    }

    private function shutdownHandler(ErrorHandlerInterface $errorHandler, bool $displayDetails): ShutdownHandler
    {
        return new ShutdownHandler(
            (new ServerRequestFactory())->createServerRequest('GET', '/resource'),
            $errorHandler,
            new HttpHeaderSettings(new Settings([
                'headers' => [
                    'Access-Control-Allow-Origin' => 'https://app.example.com',
                    'Access-Control-Allow-Headers' => 'Content-Type',
                ],
            ])),
            $displayDetails,
            $this->createStub(LoggerInterface::class),
        );
    }

    private static function setUserError(int $type): void
    {
        $level = error_reporting(0);
        trigger_error('shutdown failure', $type);
        error_reporting($level);
    }
}
