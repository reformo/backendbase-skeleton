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
    /** @var array{'project-id': string, 'credentials-path': string, 'cdn-base-url': string, 'timeout-seconds': float} */
    private array $values;

    public function __construct(mixed $settings)
    {
        if (! is_array($settings)) {
            throw new UnexpectedValueException('Push settings must be an array.');
        }

        $projectId       = $settings['project-id'] ?? '';
        $credentialsPath = $settings['credentials-path'] ?? '';
        $cdnBaseUrl      = $settings['cdn-base-url'] ?? '';
        $timeout         = $settings['timeout-seconds'] ?? 5.0;
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
            'project-id' => $projectId,
            'credentials-path' => $credentialsPath,
            'cdn-base-url' => $cdnBaseUrl,
            'timeout-seconds' => (float) $timeout,
        ];
    }

    public function enabled(): bool
    {
        return $this->values['project-id'] !== '';
    }

    public function projectId(): string
    {
        return $this->values['project-id'];
    }

    public function credentialsPath(): string
    {
        return $this->values['credentials-path'];
    }

    public function cdnBaseUrl(): string
    {
        return $this->values['cdn-base-url'];
    }

    public function timeoutSeconds(): float
    {
        return $this->values['timeout-seconds'];
    }
}
