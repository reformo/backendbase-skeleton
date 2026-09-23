<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Notification;

use UnexpectedValueException;

use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;

final readonly class PushSettings
{
    /** @var array{projectId: string, credentialsPath: string, cdnBaseUrl: string, timeoutSeconds: float} */
    private array $values;

    public function __construct(mixed $settings)
    {
        if (! is_array($settings)) {
            throw new UnexpectedValueException('Push settings must be an array.');
        }

        $projectId       = $settings['projectId'] ?? '';
        $credentialsPath = $settings['credentialsPath'] ?? '';
        $cdnBaseUrl      = $settings['cdnBaseUrl'] ?? '';
        $timeout         = $settings['timeoutSeconds'] ?? 5.0;
        if (! is_string($projectId) || ! is_string($credentialsPath) || ! is_string($cdnBaseUrl)) {
            throw new UnexpectedValueException('Push settings must contain strings.');
        }

        if ($projectId !== '' && preg_match('/^[a-z0-9-]{1,128}$/', $projectId) !== 1) {
            throw new UnexpectedValueException('Firebase project ID is invalid.');
        }

        if ((! is_float($timeout) && ! is_int($timeout)) || ! is_finite((float) $timeout) || $timeout <= 0) {
            throw new UnexpectedValueException('Push timeout must be positive.');
        }

        $this->values = [
            'projectId' => $projectId,
            'credentialsPath' => $credentialsPath,
            'cdnBaseUrl' => $cdnBaseUrl,
            'timeoutSeconds' => (float) $timeout,
        ];
    }

    public function enabled(): bool
    {
        return $this->values['projectId'] !== '';
    }

    public function projectId(): string
    {
        return $this->values['projectId'];
    }

    public function credentialsPath(): string
    {
        return $this->values['credentialsPath'];
    }

    public function cdnBaseUrl(): string
    {
        return $this->values['cdnBaseUrl'];
    }

    public function timeoutSeconds(): float
    {
        return $this->values['timeoutSeconds'];
    }
}
