<?php

declare(strict_types=1);

namespace Backendbase\Shared\Configuration;

use Backendbase\Shared\Settings;
use UnexpectedValueException;

use function array_key_exists;
use function hash_equals;
use function is_array;
use function is_string;

final readonly class ApiKeySettings
{
    /** @var array<string, string|null> */
    private array $apiKeys;

    public function __construct(Settings $settings)
    {
        $configuration = $settings->get();
        if (! is_array($configuration)) {
            throw new UnexpectedValueException('The application settings are invalid.');
        }

        $apiKeys = [];
        foreach ($configuration as $apiName => $apiSettings) {
            if (! is_string($apiName) || ! is_array($apiSettings) || ! array_key_exists('api-key', $apiSettings)) {
                continue;
            }

            $validated         = ValidatedHttpSettings::api($apiSettings);
            $apiKeys[$apiName] = $validated['api-key'];
        }

        $this->apiKeys = $apiKeys;
    }

    public function accepts(string $apiName, string $candidate): bool
    {
        $configured = $this->apiKeys[$apiName] ?? null;

        return $configured !== null && $configured !== '' && hash_equals($configured, $candidate);
    }
}
