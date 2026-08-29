<?php

declare(strict_types=1);

namespace Backendbase\Shared\Configuration;

use Backendbase\Shared\Settings;
use UnexpectedValueException;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function in_array;

final readonly class HttpHeaderSettings
{
    /** @var non-empty-list<string> */
    private array $allowedOrigins;

    private string $allowedHeaders;

    public function __construct(Settings $settings)
    {
        $headers = ValidatedHttpSettings::headers($settings->get('headers'));
        $origins = array_filter(array_map('trim', explode(',', $headers['Access-Control-Allow-Origin'])));
        if ($origins === []) {
            throw new UnexpectedValueException('At least one HTTP origin must be configured.');
        }

        $this->allowedOrigins = array_values($origins);
        $this->allowedHeaders = $headers['Access-Control-Allow-Headers'];
    }

    public function allowedOrigin(string|null $requestOrigin): string
    {
        if ($requestOrigin !== null && in_array($requestOrigin, $this->allowedOrigins, true)) {
            return $requestOrigin;
        }

        return $this->allowedOrigins[count($this->allowedOrigins) - 1];
    }

    public function allowedHeaders(): string
    {
        return $this->allowedHeaders;
    }
}
