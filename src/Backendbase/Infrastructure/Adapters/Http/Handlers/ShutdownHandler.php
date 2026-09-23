<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Handlers;

use Backendbase\Infrastructure\Adapters\Http\ResponseEmitter\ResponseEmitter;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Interfaces\ErrorHandlerInterface;

use function error_get_last;
use function in_array;

use const E_DEPRECATED;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;

readonly class ShutdownHandler
{
    public function __construct(
        private Request $request,
        private ErrorHandlerInterface $errorHandler,
        private HttpHeaderSettings $settings,
        private bool $displayErrorDetails,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        $error = error_get_last();
        if (! $error) {
            return;
        }

        if (in_array($error['type'], [E_USER_DEPRECATED, E_DEPRECATED], true)) {
            return;
        }

        $errorFile    = $error['file'];
        $errorLine    = $error['line'];
        $errorMessage = $error['message'];
        $errorType    = $error['type'];
        $message      = 'An error while processing your request. Please try again later.';

        if ($this->displayErrorDetails) {
            switch ($errorType) {
                case E_USER_ERROR:
                    // A user fatal error terminates the PHPUnit process.
                    // @codeCoverageIgnoreStart
                    $message = self::fatalErrorMessage($errorMessage, $errorLine, $errorFile);
                    break;
                    // @codeCoverageIgnoreEnd

                case E_USER_WARNING:
                    $message = 'WARNING: ' . $errorMessage;
                    break;

                case E_USER_NOTICE:
                    $message = 'NOTICE: ' . $errorMessage;
                    break;

                default:
                    $message  = 'ERROR: ' . $errorMessage;
                    $message .= ' on line ' . $errorLine . ' in file ' . $errorFile . '.';
                    break;
            }
        }

        $this->logger->error('PHP shutdown error: ' . $errorMessage, [
            'exception' => 'PHPShutdownError',
            'file' => $errorFile,
            'line' => $errorLine,
            'type' => $errorType,
        ]);

        $exception = new HttpInternalServerErrorException($this->request, $message);
        $response  = $this->errorHandler->__invoke(
            $this->request,
            $exception,
            $this->displayErrorDetails,
            false,
            false,
        );

        $responseEmitter = new ResponseEmitter($this->settings);
        $responseEmitter->emit($response);
    }

    private static function fatalErrorMessage(string $errorMessage, int $errorLine, string $errorFile): string
    {
        return 'FATAL ERROR: ' . $errorMessage . '. '
            . ' on line ' . $errorLine . ' in file ' . $errorFile . '.';
    }
}
