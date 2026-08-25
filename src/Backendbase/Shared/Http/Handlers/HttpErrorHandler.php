<?php

declare(strict_types=1);

namespace Backendbase\Shared\Http\Handlers;

use Backendbase\Shared\Http\Actions\ActionError;
use Backendbase\Shared\ProblemDetailsException;
use Backendbase\Shared\Services\Translator;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Throwable;

use function array_key_exists;

class HttpErrorHandler extends SlimErrorHandler
{
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        LoggerInterface $logger,
        private readonly Translator|null $translator = null,
    ) {
        parent::__construct($callableResolver, $responseFactory, $logger);
    }

    #[Override]
    public function __invoke(
        ServerRequest $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): Response {
        if ($logErrors) {
            $this->logThrowable($exception);
        }

        return parent::__invoke($request, $exception, $displayErrorDetails, false, $logErrorDetails);
    }

    private function logThrowable(Throwable $exception): void
    {
        $this->logger->error($exception->getMessage(), [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /** @throws ReflectionException */
    #[Override]
    protected function respond(): Response
    {
        $exception         = $this->exception;
        $statusCode        = 500;
        $additionalHeaders = ['Content-Type' => 'application/problem+json'];
        $error             = new ActionError(
            500,
            'Internal Server Error',
            'server/internal-error',
            ActionError::SERVER_ERROR,
            'An internal error has occurred while processing your request.',
        );

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $error->setDescription($exception->getMessage());
            if ($exception instanceof HttpNotFoundException) {
                $error->setCode('http/resource-not-found');
                $error->setType(ActionError::RESOURCE_NOT_FOUND);
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $error->setCode('http/not-allowed');
                $error->setType(ActionError::NOT_ALLOWED);
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $error->setCode('http/unauthenticated');
                $error->setType(ActionError::UNAUTHENTICATED);
            } elseif ($exception instanceof HttpForbiddenException) {
                $error->setCode('http/insufficient-privileges');
                $error->setType(ActionError::INSUFFICIENT_PRIVILEGES);
            } elseif ($exception instanceof HttpBadRequestException) {
                $error->setCode('http/bad-request');
                $error->setType(ActionError::BAD_REQUEST);
            } elseif ($exception instanceof HttpNotImplementedException) {
                $error->setCode('http/not-implemented');
                $error->setType(ActionError::NOT_IMPLEMENTED);
            }
        }

        if ($exception instanceof ProblemDetailsException) {
            $statusCode = $exception->getAdditionalData()['status'] ?? $exception->getStatus();
            $error->setStatus($statusCode);
            $error->setTitle($exception->getTitle());
            $error->setDescription($exception->getMessage());
            $error->setType($exception->getType());
            $error->setCode($exception->getErrorCode());
            $error->setAdditionalData($exception->getAdditionalData());
        } elseif (
            ! ($exception instanceof HttpException)
                && $this->displayErrorDetails
        ) {
            $error->setDescription($exception->getMessage());
            $exceptionReflection         = new ReflectionClass($exception);
            $exceptionDetails            = ['exception' => $exceptionReflection->getShortName()];
            $exceptionDetails['message'] = $exception->getMessage();
            $exceptionDetails['file']    = $exception->getFile();
            $exceptionDetails['line']    = $exception->getLine();
            $exceptionDetails['trace']   = $exception->getTrace();
            $error->setAdditionalData(['exceptionDetails' => $exceptionDetails]);
        }

        $payload = $error->jsonSerialize();
        if (array_key_exists('message', $payload) && $this->translator !== null) {
            $payload['message'] = $this->translator->translate($payload['message']);
        }

        return new JsonResponse($payload, $statusCode, $additionalHeaders);
    }
}
