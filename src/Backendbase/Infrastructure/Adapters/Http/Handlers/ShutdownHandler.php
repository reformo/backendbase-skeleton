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

use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_USER_ERROR;

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

        $this->handleError($error);
    }

    /** @param array{type: int, message: string, file: string, line: int} $error */
    private function handleError(array $error): void
    {
        if (! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }

        $errorFile    = $error['file'];
        $errorLine    = $error['line'];
        $errorMessage = $error['message'];
        $errorType    = $error['type'];
        $message      = 'An error while processing your request. Please try again later.';

        if ($this->displayErrorDetails) {
            $message = self::fatalErrorMessage($errorMessage, $errorLine, $errorFile);
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
