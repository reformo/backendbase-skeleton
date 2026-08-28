<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http;

use Backendbase\Shared\Domain\Exception\DomainException;
use Backendbase\Shared\Http\Actions\ActionError;
use Backendbase\Shared\Services\Translator;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Throwable;

use function array_key_exists;

final class HttpErrorHandler extends SlimErrorHandler
{
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        LoggerInterface $logger,
        private readonly DomainErrorProblemDetailsMapper $domainErrorMapper,
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
            HttpExceptionProblemDetailsMapper::map($exception, $error);
        }

        if ($exception instanceof DomainException) {
            $error      = $this->domainErrorMapper->map($exception);
            $statusCode = $error->status();
        } elseif (! ($exception instanceof HttpException) && $this->displayErrorDetails) {
            $error->setDescription($exception->getMessage());
            $exceptionReflection = new ReflectionClass($exception);
            $error->setAdditionalData([
                'exceptionDetails' => [
                    'exception' => $exceptionReflection->getShortName(),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTrace(),
                ],
            ]);
        }

        $payload = $error->jsonSerialize();
        if ($exception instanceof DomainException && ! array_key_exists('message', $payload)) {
            $payload['message'] = $payload['detail'];
        }

        if (array_key_exists('message', $payload)) {
            $payload['message'] = ProblemDetailsMessageFormatter::format(
                (string) $payload['message'],
                $payload,
                $this->translator,
            );
        }

        return new JsonResponse($payload, $statusCode, $additionalHeaders);
    }
}
