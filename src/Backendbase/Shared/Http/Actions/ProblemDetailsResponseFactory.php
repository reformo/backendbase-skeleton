<?php

declare(strict_types=1);

namespace Backendbase\Shared\Http\Actions;

use Backendbase\Shared\ProblemDetailsException;
use Backendbase\Shared\Services\Translator;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function preg_match_all;
use function str_contains;
use function str_replace;

final class ProblemDetailsResponseFactory
{
    public static function create(
        ProblemDetailsException $exception,
        LoggerInterface $logger,
        Translator|null $translator,
    ): ResponseInterface {
        $error   = self::error($exception);
        $payload = $error->jsonSerialize();
        if (! array_key_exists('message', $payload)) {
            $payload['message'] = $payload['detail'];
        }

        $payload['message'] = self::translate((string) $payload['message'], $payload, $translator);
        $payload['message'] = self::replaceParameters($payload['message'], $payload);
        self::logServerFailure($exception, $payload, $logger);

        return new JsonResponse(
            $payload,
            $error->status(),
            ['Content-Type' => 'application/problem+json'],
        );
    }

    private static function error(ProblemDetailsException $exception): ActionError
    {
        return new ActionError(
            $exception->getStatus(),
            $exception->getTitle(),
            $exception->getErrorCode(),
            $exception->getType(),
            $exception->getMessage(),
            $exception->getAdditionalData(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return string|array<array-key, mixed>
     */
    private static function translate(string $message, array $payload, Translator|null $translator): string|array
    {
        if ($translator === null || str_contains($message, ' ')) {
            return $message;
        }

        return $translator->translate($message, $payload);
    }

    /**
     * @param string|array<array-key, mixed> $message
     * @param array<string, mixed>           $payload
     *
     * @return string|array<array-key, mixed>
     */
    private static function replaceParameters(string|array $message, array $payload): string|array
    {
        if (is_array($message)) {
            return $message;
        }

        preg_match_all('/:([a-zA-Z0-9])+/i', $message, $matches);
        if (count($matches[0]) === 0) {
            return $message;
        }

        $replaceValues = [];
        foreach ($matches[0] as $match) {
            $replaceValues[] = $payload[str_replace(':', '', $match)] ?? '';
        }

        return str_replace($matches[0], $replaceValues, $message);
    }

    /** @param array<string, mixed> $payload */
    private static function logServerFailure(
        ProblemDetailsException $exception,
        array $payload,
        LoggerInterface $logger,
    ): void {
        if (array_key_exists('status', $payload) && in_array($payload['status'], [400, 401, 403, 404], true)) {
            return;
        }

        $logger->error((string) ($payload['message'] ?? $exception->getMessage()), [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'status' => $payload['status'] ?? $exception->getStatus(),
            'error_code' => $payload['code'] ?? $exception->getErrorCode(),
        ]);
    }
}
