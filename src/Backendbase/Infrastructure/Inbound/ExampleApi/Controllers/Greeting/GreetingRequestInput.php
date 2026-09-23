<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Greeting;

use Backendbase\Shared\Exception\InvalidUserInput;

use function is_array;
use function is_string;
use function mb_check_encoding;
use function mb_strlen;
use function preg_match;
use function trim;

final class GreetingRequestInput
{
    public static function fullName(mixed $body): string
    {
        $value = is_array($body) ? ($body['fullname'] ?? null) : null;
        if (! is_string($value)) {
            throw InvalidUserInput::create('The fullname value must be a string.');
        }

        $fullName = trim($value);
        if ($fullName === '' || ! mb_check_encoding($fullName, 'UTF-8') || mb_strlen($fullName) > 100) {
            throw InvalidUserInput::create('The fullname value must contain 1 to 100 characters.');
        }

        if (preg_match('/\p{Cc}/u', $fullName) === 1) {
            throw InvalidUserInput::create('The fullname value must not contain control characters.');
        }

        return $fullName;
    }
}
